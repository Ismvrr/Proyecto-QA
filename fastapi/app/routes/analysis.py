"""Manual Gemini analysis endpoints for conversations and periods."""

import asyncio
import json
import logging
from typing import Optional

from fastapi import APIRouter, Depends, Header, HTTPException
from pydantic import BaseModel, Field

from app.database import get_db
from app.services.gemini import (
    GeminiConfigurationError,
    GeminiDisabledError,
    GeminiQuotaError,
    analyze_text,
)
from app.services.jwt import get_current_user

router = APIRouter(prefix="/api/analyze", tags=["analysis"])
logger = logging.getLogger(__name__)
SERVER_KEY_MINUTE_LIMIT = 2
SERVER_KEY_DAILY_LIMIT = 30


class ConversationAnalysisRequest(BaseModel):
    company_id: int
    dialog_id: str
    request_id: Optional[str] = None
    year: int = Field(..., ge=2020, le=2030)
    month: int = Field(..., ge=1, le=12)
    client_prompt_id: Optional[int] = None
    prompt_text: str = Field(..., min_length=10, max_length=12000)


class PeriodAnalysisRequest(BaseModel):
    company_id: int
    year: int = Field(..., ge=2020, le=2030)
    month: int = Field(..., ge=1, le=12)
    client_prompt_id: Optional[int] = None
    prompt_text: str = Field(..., min_length=10, max_length=12000)
    max_conversations: Optional[int] = Field(default=1, ge=1, le=10000)
    full_month: bool = False
    consolidate: bool = False


def _transcript(cursor, request: ConversationAnalysisRequest) -> list[dict]:
    query = """SELECT id, request_id, dialog_id, tipo, texto, fecha_creacion
               FROM mensajes_request
               WHERE company_id=%s AND dialog_id=%s
                 AND YEAR(fecha_creacion)=%s AND MONTH(fecha_creacion)=%s"""
    params: list = [request.company_id, request.dialog_id, request.year, request.month]
    if request.request_id:
        query += " AND request_id=%s"
        params.append(request.request_id)
    query += " ORDER BY fecha_creacion ASC, id ASC"
    cursor.execute(query, params)
    return cursor.fetchall()


def _replace_prompt_variables(instructions: str, company: dict, transcript: str) -> str:
    company_name = company.get("name") or "No especificado"
    context = "; ".join(
        value for value in [
            f"Empresa: {company_name}",
            f"Estado: {company.get('status') or 'No especificado'}",
            f"Modo: {company.get('company_mode') or 'No especificado'}",
            f"Idioma: {company.get('lang') or 'No especificado'}",
            f"Zona horaria: {company.get('timezone') or 'No especificado'}",
        ]
    )
    values = {
        "company_name": company_name,
        "industry": company.get("company_mode") or "No especificada",
        "company_context": context,
        "company_services": company.get("subscription_addons") or "No especificados",
        "business_model": company.get("subscription_type") or "No especificado",
        "campaign_context": "No especificado",
        "bot_flow": "No especificado",
        "channels": "Chat2Desk; canal específico no indicado en la configuración",
        "analysis_objective": "Evaluar la conversación y detectar oportunidades de mejora.",
        "success_criteria": "Respuesta pertinente, continuidad del flujo y siguiente acción clara.",
        "fields_to_extract": "Intención, etapa, objeciones, resultado, fricciones y recomendaciones.",
        "conversation": transcript,
    }
    for key, value in values.items():
        instructions = instructions.replace("{{" + key + "}}", str(value))
    return instructions


def _transcript_text(messages: list[dict]) -> str:
    return "\n".join(
        f"[{message['fecha_creacion']}] {message['tipo']}: {message['texto'] or ''}"
        for message in messages
    )


def _build_prompt(instructions: str, messages: list[dict], company: dict) -> str:
    transcript = _transcript_text(messages)
    prompt = _replace_prompt_variables(instructions.strip(), company, transcript)
    if "CONVERSACIÓN:" not in prompt and "CONVERSACION:" not in prompt:
        prompt = f"{prompt}\n\nCONVERSACION A ANALIZAR:\n{transcript}"
    return prompt


def _load_company(cursor, company_id: int) -> dict:
    cursor.execute(
        """SELECT name, status, company_mode, lang, timezone,
                  subscription_type, subscription_addons
           FROM companies WHERE id=%s""",
        (company_id,),
    )
    company = cursor.fetchone()
    if not company:
        raise HTTPException(status_code=404, detail="Company not found")
    return company


def _save_consolidation(
    request: PeriodAnalysisRequest,
    results: list[dict],
    api_key: Optional[str],
    user: dict,
) -> dict:
    source = "byok" if api_key else "server"
    source_text = "\n\n--- RESULTADO ---\n".join(item["result"] for item in results)
    prompt = (
        "Actúa como analista senior. Consolida los resultados de análisis de conversaciones "
        "del periodo indicado. No inventes datos. Entrega patrones, hallazgos repetidos, "
        "problemas prioritarios y recomendaciones accionables.\n\n"
        f"PERIODO: {request.year}-{request.month:02d}\n"
        f"RESULTADOS DE CONVERSACIONES:\n{source_text}"
    )

    with get_db() as conn:
        with conn.cursor() as cursor:
            _load_company(cursor, request.company_id)
            if not api_key:
                _enforce_server_key_rate_limit(cursor, request.company_id)
            cursor.execute(
                """INSERT INTO analysis_jobs
                   (company_id, client_prompt_id, year, month, status, prompt_snapshot,
                    gemini_key_source, started_at)
                   VALUES (%s, %s, %s, %s, 'running', %s, %s, NOW())""",
                (request.company_id, request.client_prompt_id, request.year, request.month, prompt, source),
            )
            job_id = cursor.lastrowid
            conn.commit()

    try:
        result = analyze_text(prompt, api_key)
    except GeminiDisabledError as exc:
        _mark_job_error(job_id, str(exc))
        raise HTTPException(status_code=409, detail=str(exc))
    except GeminiQuotaError as exc:
        _mark_job_error(job_id, str(exc))
        raise HTTPException(status_code=429, detail=str(exc))
    except GeminiConfigurationError as exc:
        _mark_job_error(job_id, str(exc))
        raise HTTPException(status_code=401, detail=str(exc))

    with get_db() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                """UPDATE analysis_jobs
                   SET status='completed', prompt2_result=%s, gemini_tokens_used=%s,
                       input_tokens=%s, output_tokens=%s, completed_at=NOW()
                   WHERE id=%s""",
                (
                    json.dumps({"text": result.text}, ensure_ascii=False),
                    result.total_tokens,
                    result.input_tokens,
                    result.output_tokens,
                    job_id,
                ),
            )
            conn.commit()

    return {
        "job_id": job_id,
        "result": result.text,
        "tokens": {
            "input": result.input_tokens,
            "output": result.output_tokens,
            "total": result.total_tokens,
        },
    }
    lines = [
        instructions.strip(),
        "",
        "CONVERSACION A ANALIZAR:",
    ]
    lines.extend(
        f"[{message['fecha_creacion']}] {message['tipo']}: {message['texto'] or ''}"
        for message in messages
    )
    return "\n".join(lines)


def _enforce_server_key_rate_limit(cursor, company_id: int) -> None:
    """Limit shared server-key usage without affecting BYOK requests."""
    cursor.execute(
        """SELECT COUNT(*) AS total
           FROM analysis_jobs
           WHERE company_id=%s AND gemini_key_source='server'
             AND started_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)""",
        (company_id,),
    )
    if cursor.fetchone()["total"] >= SERVER_KEY_MINUTE_LIMIT:
        raise HTTPException(
            status_code=429,
            detail="Límite de 2 análisis por minuto alcanzado para la clave del servidor.",
            headers={"Retry-After": "60"},
        )

    cursor.execute(
        """SELECT COUNT(*) AS total
           FROM analysis_jobs
           WHERE company_id=%s AND gemini_key_source='server'
             AND started_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)""",
        (company_id,),
    )
    if cursor.fetchone()["total"] >= SERVER_KEY_DAILY_LIMIT:
        raise HTTPException(
            status_code=429,
            detail="Límite de 30 análisis diarios alcanzado para la clave del servidor.",
            headers={"Retry-After": "3600"},
        )


@router.post("/conversation")
async def analyze_conversation(
    request: ConversationAnalysisRequest,
    x_gemini_api_key: Optional[str] = Header(default=None),
    user: dict = Depends(get_current_user),
):
    """Analyzes one conversation only when explicitly requested by the user."""
    with get_db() as conn:
        with conn.cursor() as cursor:
            messages = _transcript(cursor, request)
            if not messages:
                raise HTTPException(status_code=404, detail="Conversation has no extracted messages")
            company = _load_company(cursor, request.company_id)
            if not x_gemini_api_key:
                _enforce_server_key_rate_limit(cursor, request.company_id)
            prompt = _build_prompt(request.prompt_text, messages, company)

            cursor.execute(
                """INSERT INTO analysis_jobs
                   (company_id, request_id, client_prompt_id, year, month, status, prompt_snapshot,
                    gemini_key_source, started_at)
                   VALUES (%s, %s, %s, %s, %s, 'running', %s, %s, NOW())""",
                (
                    request.company_id,
                    request.request_id,
                    request.client_prompt_id,
                    request.year,
                    request.month,
                    prompt,
                    "byok" if x_gemini_api_key else "server",
                ),
            )
            job_id = cursor.lastrowid
            conn.commit()

    try:
        result = await asyncio.to_thread(analyze_text, prompt, x_gemini_api_key)
    except GeminiDisabledError as exc:
        _mark_job_error(job_id, str(exc))
        raise HTTPException(status_code=409, detail=str(exc))
    except GeminiQuotaError as exc:
        _mark_job_error(job_id, str(exc))
        raise HTTPException(status_code=429, detail=str(exc))
    except GeminiConfigurationError as exc:
        _mark_job_error(job_id, str(exc))
        raise HTTPException(status_code=401, detail=str(exc))
    except Exception as exc:
        logger.exception("Gemini analysis failed")
        _mark_job_error(job_id, str(exc)[:500])
        raise HTTPException(status_code=502, detail="Gemini analysis failed")

    with get_db() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                """UPDATE analysis_jobs
                   SET status='completed', prompt1_result=%s, gemini_tokens_used=%s,
                       input_tokens=%s, output_tokens=%s, completed_at=NOW()
                   WHERE id=%s""",
                (
                    json.dumps({"text": result.text}, ensure_ascii=False),
                    result.total_tokens,
                    result.input_tokens,
                    result.output_tokens,
                    job_id,
                ),
            )
            conn.commit()

    return {
        "job_id": job_id,
        "status": "completed",
        "dialog_id": request.dialog_id,
        "request_id": request.request_id,
        "result": result.text,
        "tokens": {
            "input": result.input_tokens,
            "output": result.output_tokens,
            "total": result.total_tokens,
        },
    }


@router.post("/period")
async def analyze_period(
    request: PeriodAnalysisRequest,
    x_gemini_api_key: Optional[str] = Header(default=None),
    user: dict = Depends(get_current_user),
):
    """
    Analyzes a manually selected period with either an explicit conversation
    limit or every conversation extracted for that month.

    The default limit is one conversation so a first test cannot accidentally
    process a full month and spend tokens unexpectedly. ``full_month`` is an
    explicit opt-in for the complete extracted period.
    """
    with get_db() as conn:
        with conn.cursor() as cursor:
            query = """SELECT dialog_id, request_id
                       FROM mensajes_request
                       WHERE company_id=%s AND YEAR(fecha_creacion)=%s AND MONTH(fecha_creacion)=%s
                       GROUP BY dialog_id, request_id
                       ORDER BY MAX(fecha_creacion) DESC"""
            params = [request.company_id, request.year, request.month]
            if not request.full_month:
                query += " LIMIT %s"
                params.append(request.max_conversations or 1)
            cursor.execute(query, params)
            conversations = cursor.fetchall()

    results = []
    for conversation in conversations:
        conversation_request = ConversationAnalysisRequest(
            company_id=request.company_id,
            dialog_id=conversation["dialog_id"],
            request_id=conversation["request_id"],
            year=request.year,
            month=request.month,
            client_prompt_id=request.client_prompt_id,
            prompt_text=request.prompt_text,
        )
        results.append(await analyze_conversation(conversation_request, x_gemini_api_key, user))

    consolidation = None
    if (request.consolidate or request.full_month) and len(results) > 1:
        consolidation = await asyncio.to_thread(
            _save_consolidation,
            request,
            results,
            x_gemini_api_key,
            user,
        )

    return {
        "status": "completed",
        "year": request.year,
        "month": request.month,
        "processed": len(results),
        "results": results,
        "consolidation": consolidation,
    }


def _mark_job_error(job_id: int, message: str) -> None:
    with get_db() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                "UPDATE analysis_jobs SET status='error', error_message=%s WHERE id=%s",
                (message[:500], job_id),
            )
            conn.commit()
