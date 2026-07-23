"""
Webhook Routes - API_C2D

Receptor de webhooks de Chat2Desk para sincronizacion en tiempo real.

Diseno multi-cliente:
    - Todos los clientes usan la misma URL
    - La empresa se identifica por el token del header Authorization
    - El mensaje se guarda en mensajes_request con deduplicacion por mensaje_id

Eventos MVP soportados:
    - inbox
    - outbox
    - imported_message
"""

import logging
import hashlib
from datetime import datetime, timezone

from fastapi import APIRouter, Header, HTTPException, Request

from app.database import get_db

router = APIRouter(prefix="/api/webhooks", tags=["webhooks"])
logger = logging.getLogger(__name__)

SUPPORTED_EVENTS = {"inbox", "outbox", "imported_message"}

INSERT_MESSAGE_SQL = """
INSERT IGNORE INTO mensajes_request
    (request_id, dialog_id, mensaje_id, client_id, operator_id, tipo, texto, transport, fecha_creacion)
VALUES
    (%s, %s, %s, %s, %s, %s, %s, %s, %s)
"""


def _normalize_token(raw_token: str | None) -> str:
    """Normaliza el header Authorization para comparar contra companies.api_token."""
    if not raw_token:
        return ""

    token = raw_token.strip()
    if token.lower().startswith("bearer "):
        token = token[7:].strip()
    return token


def _parse_event_time(event_time: str | None) -> str | None:
    """Convierte el timestamp ISO del webhook al formato DATETIME de MySQL."""
    if not event_time:
        return None

    try:
        dt = datetime.fromisoformat(event_time.replace("Z", "+00:00"))
        return dt.astimezone(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
    except ValueError:
        logger.warning("Invalid webhook event_time", extra={"event_time": event_time})
        return None


def _map_webhook_message(payload: dict) -> dict | None:
    """Mapea el payload de C2D al schema de mensajes_request."""
    hook_type = payload.get("hook_type")
    if hook_type not in SUPPORTED_EVENTS:
        return None

    return {
        "request_id": str(payload.get("request_id", "")),
        "dialog_id": str(payload.get("dialog_id", "")),
        "mensaje_id": str(payload.get("message_id", "")),
        "client_id": str(payload.get("client_id", "")),
        "operator_id": str(payload.get("operator_id", "")),
        "tipo": payload.get("type", ""),
        "texto": payload.get("text", ""),
        "transport": payload.get("transport", ""),
        "fecha_creacion": _parse_event_time(payload.get("event_time")),
    }


@router.post("/c2d")
async def receive_c2d_webhook(
    request: Request,
    authorization: str | None = Header(default=None),
):
    """
    Recibe un webhook de Chat2Desk y lo guarda en la base de datos.

    Comportamiento:
        - 401 si el token no corresponde a ninguna empresa
        - 200 si el evento no es parte del MVP (se ignora de forma segura)
        - 200 si el mensaje se guardo o ya existia por deduplicacion
    """
    payload = await request.json()
    token = _normalize_token(authorization)

    if not token:
        raise HTTPException(status_code=401, detail="Missing Authorization token")

    mapped_message = _map_webhook_message(payload)
    hook_type = payload.get("hook_type")

    with get_db() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                "SELECT id, name FROM companies WHERE api_token_hash = %s AND isdeleted = 0 LIMIT 1",
                (hashlib.sha256(token.encode()).hexdigest(),),
            )
            company = cursor.fetchone()

            if not company:
                raise HTTPException(status_code=401, detail="Invalid webhook token")

            if mapped_message is None:
                logger.info(
                    "webhook_ignored",
                    extra={
                        "company_id": company["id"],
                        "hook_type": hook_type,
                    },
                )
                return {
                    "status": "ignored",
                    "reason": "unsupported_hook_type",
                    "hook_type": hook_type,
                }

            cursor.execute(
                INSERT_MESSAGE_SQL,
                (
                    mapped_message["request_id"],
                    mapped_message["dialog_id"],
                    mapped_message["mensaje_id"],
                    mapped_message["client_id"],
                    mapped_message["operator_id"],
                    mapped_message["tipo"],
                    mapped_message["texto"],
                    mapped_message["transport"],
                    mapped_message["fecha_creacion"],
                ),
            )
            conn.commit()

            inserted = cursor.rowcount > 0
            logger.info(
                "webhook_received",
                extra={
                    "company_id": company["id"],
                    "hook_type": hook_type,
                    "message_id": mapped_message["mensaje_id"],
                    "inserted": inserted,
                },
            )

    return {
        "status": "ok",
        "hook_type": hook_type,
        "message_id": mapped_message["mensaje_id"],
        "inserted": inserted,
    }
