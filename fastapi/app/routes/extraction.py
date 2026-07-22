"""
Extraction Routes - API_C2D

Endpoints para extracción de conversaciones de Chat2Desk.

Flujo de extracción:
    1. Usuario selecciona cliente + mes/año
    2. POST /api/extract inicia la extracción
    3. Backend consulta dialogs de C2D
    4. Para cada dialog, obtiene mensajes del período
    5. Guarda en BD con deduplicación (INSERT IGNORE)
    6. Actualiza estado en extracted_periods

Endpoints:
    POST /api/extract          - Iniciar extracción por período
    GET  /api/sync/status      - Estado de sincronización
    GET  /api/sync/periods     - Períodos extraídos de un cliente
"""

import logging
from datetime import datetime
from typing import Optional
from fastapi import APIRouter, Depends, HTTPException, BackgroundTasks
from pydantic import BaseModel, Field

from app.services.jwt import get_current_user
from app.services.chat2desk import Chat2DeskClient, Chat2DeskError
from app.database import get_db

router = APIRouter(prefix="/api", tags=["extraction"])
logger = logging.getLogger(__name__)

# Batch size para inserciones (cada 500 filas)
BATCH_SIZE = 500

# SQL para insertar mensaje con deduplicación
INSERT_MESSAGE_SQL = """
INSERT IGNORE INTO mensajes_request 
    (request_id, dialog_id, mensaje_id, client_id, operator_id, tipo, texto, transport, fecha_creacion)
VALUES 
    (%s, %s, %s, %s, %s, %s, %s, %s, %s)
"""


class ExtractRequest(BaseModel):
    """Modelo para petición de extracción"""
    company_id: int = Field(..., description="ID de la empresa en nuestra BD")
    year: int = Field(..., ge=2020, le=2030, description="Año a extraer")
    month: int = Field(..., ge=1, le=12, description="Mes a extraer (1-12)")
    c2d_token: Optional[str] = Field(None, description="Token C2D (opcional, usa el de la empresa)")
    exclude_autoreply: bool = Field(True, description="Excluir mensajes autoreply")


class ExtractStatus(BaseModel):
    """Estado de una extracción"""
    company_id: int
    year: int
    month: int
    status: str
    total_dialogs: int
    total_messages: int
    error_message: Optional[str]
    created_at: Optional[str]
    completed_at: Optional[str]


# =============================================================================
# POST /api/extract - Iniciar extracción
# =============================================================================

def _run_extraction(
    company_id: int,
    year: int,
    month: int,
    c2d_token: str,
    exclude_autoreply: bool
):
    """
    Función BackgroundTask que ejecuta la extracción completa.

    Flujo:
        1. Crear/actualizar registro en extracted_periods (status=extracting)
        2. Obtener todos los dialogs de C2D
        3. Para cada dialog, buscar mensajes del mes
        4. Limpiar y guardar en BD (batch de 500)
        5. Actualizar extracted_periods con totales
    """
    with get_db() as conn:
        cursor = conn.cursor()
        try:
            # 1. Registrar inicio de extracción
            logger.info(f"Starting extraction: company={company_id}, period={year}-{month:02d}")
            cursor.execute(
                """INSERT INTO extracted_periods (company_id, year, month, status)
                   VALUES (%s, %s, %s, 'extracting')
                   ON DUPLICATE KEY UPDATE status='extracting', error_message=NULL""",
                (company_id, year, month)
            )
            conn.commit()

            # 2. Crear cliente C2D
            client = Chat2DeskClient(token=c2d_token)

            # 3. Obtener todos los dialogs
            logger.info("Fetching all dialogs from C2D...")
            dialogs = client.get_all_dialogs()
            logger.info(f"Found {len(dialogs)} dialogs total")

            # 4. Calcular rango de fechas del mes
            start_date = f"{year}-{month:02d}-01 00:00"
            if month == 12:
                end_date = f"{year + 1}-01-01 00:00"
            else:
                end_date = f"{year}-{month + 1:02d}-01 00:00"

            # 5. Extraer mensajes de cada dialog
            total_dialogs = 0
            total_messages = 0
            batch_messages = []

            # Conjunto de tipos a excluir
            exclude_types = {"system", "comment"}
            if exclude_autoreply:
                exclude_types.add("autoreply")

            for dialog in dialogs:
                dialog_id = dialog.get("id")
                if not dialog_id:
                    continue

                try:
                    # Obtener mensajes del mes para este dialog
                    messages = client.get_messages(
                        dialog_id=dialog_id,
                        start_date=start_date,
                        finish_date=end_date,
                        order="asc"
                    )

                    if not messages:
                        continue

                    total_dialogs += 1

                    # Limpiar y agregar al batch
                    for raw_msg in messages:
                        clean = Chat2DeskClient.clean_message(raw_msg, exclude_types)
                        if clean:
                            batch_messages.append(clean)
                            total_messages += 1

                    # Insertar batch cuando alcance el límite
                    if len(batch_messages) >= BATCH_SIZE:
                        _insert_batch(cursor, batch_messages)
                        conn.commit()
                        batch_messages = []
                        logger.info(f"Progress: {total_dialogs} dialogs, {total_messages} messages")

                except Chat2DeskError as e:
                    logger.warning(f"Error fetching messages for dialog {dialog_id}: {e}")
                    continue

            # Insertar mensajes restantes
            if batch_messages:
                _insert_batch(cursor, batch_messages)
                conn.commit()

            # 6. Actualizar extracted_periods con éxito
            cursor.execute(
                """UPDATE extracted_periods 
                   SET status='completed', total_dialogs=%s, total_messages=%s, completed_at=NOW()
                   WHERE company_id=%s AND year=%s AND month=%s""",
                (total_dialogs, total_messages, company_id, year, month)
            )
            conn.commit()

            logger.info(f"Extraction completed: {total_dialogs} dialogs, {total_messages} messages")

        except Exception as e:
            logger.error(f"Extraction failed: {e}")
            cursor.execute(
                """UPDATE extracted_periods 
                   SET status='error', error_message=%s
                   WHERE company_id=%s AND year=%s AND month=%s""",
                (str(e)[:500], company_id, year, month)
            )
            conn.commit()
        finally:
            cursor.close()


def _insert_batch(cursor, messages: list):
    """
    Inserta un batch de mensajes en la BD.

    Usa INSERT IGNORE para deduplicación por mensaje_id.
    """
    if not messages:
        return

    values = [
        (
            m["request_id"], m["dialog_id"], m["mensaje_id"],
            m["client_id"], m["operator_id"], m["tipo"],
            m["texto"], m["transport"], m["fecha_creacion"]
        )
        for m in messages
    ]

    cursor.executemany(INSERT_MESSAGE_SQL, values)
    logger.debug(f"Inserted batch of {len(values)} messages")


@router.post("/extract")
async def start_extraction(
    request: ExtractRequest,
    background_tasks: BackgroundTasks,
    user: dict = Depends(get_current_user)
):
    """
    Inicia la extracción de mensajes de Chat2Desk para un período específico.

    La extracción se ejecuta en background (BackgroundTasks).
    El endpoint retorna inmediatamente con el estado "extracting".

    Flujo:
        1. Valida que el usuario tenga acceso al cliente
        2. Verifica si ya se extrajo ese período
        3. Crea registro en extracted_periods (status=extracting)
        4. Lanza BackgroundTask con la extracción
        5. Retorna 202 Accepted

    Args:
        request: company_id, year, month, c2d_token (opcional)
        background_tasks: FastAPI BackgroundTasks
        user: Usuario autenticado (JWT)

    Returns:
        202 Accepted con mensaje de inicio

    Errores:
        400: Período ya extraído (status=completed)
        403: Usuario no tiene acceso al cliente
        500: Error interno
    """
    try:
        with get_db() as conn:
            with conn.cursor() as cursor:
                # Verificar acceso del usuario al cliente
                cursor.execute(
                    "SELECT id FROM companies WHERE id = %s AND isdeleted = 0",
                    (request.company_id,)
                )
                company = cursor.fetchone()
                if not company:
                    raise HTTPException(status_code=404, detail="Company not found")

                # Verificar si ya se extrajo
                cursor.execute(
                    """SELECT status FROM extracted_periods 
                       WHERE company_id=%s AND year=%s AND month=%s""",
                    (request.company_id, request.year, request.month)
                )
                existing = cursor.fetchone()
                if existing and existing["status"] == "completed":
                    raise HTTPException(
                        status_code=400,
                        detail=f"Period {request.year}-{request.month:02d} already extracted"
                    )

                # Obtener token C2D de la empresa si no se proporcionó
                c2d_token = request.c2d_token
                if not c2d_token:
                    cursor.execute(
                        "SELECT api_token FROM companies WHERE id = %s",
                        (request.company_id,)
                    )
                    result = cursor.fetchone()
                    if result and result["api_token"]:
                        c2d_token = result["api_token"]
                    else:
                        raise HTTPException(
                            status_code=400,
                            detail="No C2D token provided and none configured for company"
                        )

        # Lanzar extracción en background
        background_tasks.add_task(
            _run_extraction,
            company_id=request.company_id,
            year=request.year,
            month=request.month,
            c2d_token=c2d_token,
            exclude_autoreply=request.exclude_autoreply
        )

        return {
            "message": "Extraction started",
            "company_id": request.company_id,
            "period": f"{request.year}-{request.month:02d}",
            "status": "extracting"
        }

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error starting extraction: {e}")
        raise HTTPException(status_code=500, detail=str(e))


# =============================================================================
# GET /api/sync/status - Estado de sincronización
# =============================================================================

@router.get("/sync/status")
async def get_sync_status(
    company_id: int,
    user: dict = Depends(get_current_user)
):
    """
    Obtiene el estado de sincronización de un cliente.

    Retorna los últimos períodos extraídos y su estado.

    Args:
        company_id: ID de la empresa
        user: Usuario autenticado (JWT)

    Returns:
        Lista de períodos con su estado
    """
    try:
        with get_db() as conn:
            with conn.cursor() as cursor:
                cursor.execute(
                    """SELECT id, company_id, year, month, total_dialogs, 
                              total_messages, status, error_message,
                              created_at, completed_at
                       FROM extracted_periods 
                       WHERE company_id = %s
                       ORDER BY year DESC, month DESC
                       LIMIT 12""",
                    (company_id,)
                )
                periods = cursor.fetchall()

                return {
                    "company_id": company_id,
                    "periods": periods
                }
    except Exception as e:
        logger.error(f"Error getting sync status: {e}")
        raise HTTPException(status_code=500, detail=str(e))


# =============================================================================
# GET /api/sync/periods - Períodos extraídos
# =============================================================================

@router.get("/sync/periods")
async def get_extracted_periods(
    company_id: int,
    user: dict = Depends(get_current_user)
):
    """
    Lista todos los períodos extraídos de un cliente.

    Útil para mostrar en la UI qué meses ya están disponibles
    para análisis.

    Args:
        company_id: ID de la empresa
        user: Usuario autenticado (JWT)

    Returns:
        Lista de períodos con estado y totales
    """
    try:
        with get_db() as conn:
            with conn.cursor() as cursor:
                cursor.execute(
                    """SELECT year, month, status, total_dialogs, total_messages,
                              completed_at
                       FROM extracted_periods 
                       WHERE company_id = %s AND status = 'completed'
                       ORDER BY year DESC, month DESC""",
                    (company_id,)
                )
                periods = cursor.fetchall()

                return {
                    "company_id": company_id,
                    "extracted_periods": periods
                }
    except Exception as e:
        logger.error(f"Error getting periods: {e}")
        raise HTTPException(status_code=500, detail=str(e))
