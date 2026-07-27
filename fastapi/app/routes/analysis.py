"""Manual Gemini analysis endpoints for conversations and periods."""

import asyncio
import json
import logging
from typing import Optional

from fastapi import APIRouter, Depends, Header, HTTPException
from pydantic import BaseModel, Field

from app.database import get_db
from app.services.gemini import GeminiDisabledError, GeminiQuotaError, analyze_text
from app.services.jwt import get_current_user

router = APIRouter(prefix="/api/analyze", tags=["analysis"])
logger = logging.getLogger(__name__)


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
    max_conversations: int = Field(1, ge=1, le=100)


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


def _build_prompt(instructions: str, messages: list[dict]) -> str:
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

            cursor.execute(
                """INSERT INTO analysis_jobs
                   (company_id, request_id, client_prompt_id, year, month, status, prompt_snapshot, started_at)
                   VALUES (%s, %s, %s, %s, %s, 'running', %s, NOW())""",
                (
                    request.company_id,
                    request.request_id,
                    request.client_prompt_id,
                    request.year,
                    request.month,
                    request.prompt_text,
                ),
            )
            job_id = cursor.lastrowid
            conn.commit()

    try:
        prompt = _build_prompt(request.prompt_text, messages)
        result = await asyncio.to_thread(analyze_text, prompt, x_gemini_api_key)
    except GeminiDisabledError as exc:
        _mark_job_error(job_id, str(exc))
        raise HTTPException(status_code=409, detail=str(exc))
    except GeminiQuotaError as exc:
        _mark_job_error(job_id, str(exc))
        raise HTTPException(status_code=429, detail=str(exc))
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
    Analyzes a manually selected period with an explicit conversation limit.

    The default limit is one conversation so a first test cannot accidentally
    process a full month and spend tokens unexpectedly.
    """
    with get_db() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                """SELECT dialog_id, request_id
                   FROM mensajes_request
                   WHERE company_id=%s AND YEAR(fecha_creacion)=%s AND MONTH(fecha_creacion)=%s
                   GROUP BY dialog_id, request_id
                   ORDER BY MAX(fecha_creacion) DESC
                   LIMIT %s""",
                (request.company_id, request.year, request.month, request.max_conversations),
            )
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

    return {
        "status": "completed",
        "year": request.year,
        "month": request.month,
        "processed": len(results),
        "results": results,
    }


def _mark_job_error(job_id: int, message: str) -> None:
    with get_db() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                "UPDATE analysis_jobs SET status='error', error_message=%s WHERE id=%s",
                (message[:500], job_id),
            )
            conn.commit()
