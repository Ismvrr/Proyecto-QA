"""
API_C2D - FastAPI Application

Aplicación principal para la plataforma de análisis de conversaciones
de Chat2Desk con inteligencia artificial.

Arquitectura:
    - Laravel (Auth): Puerto 8080 - Maneja login y sesiones
    - FastAPI (API): Puerto 8000 - API REST + análisis AI
    - Nginx (Proxy): Puerto 443 - Enrutamiento HTTPS
    - MySQL: Puerto 3306 - Base de datos remota

Endpoints principales:
    GET  /health            - Health check del sistema
    GET  /                  - Info de la API
    GET  /api/docs          - Swagger UI
    GET  /api/redoc         - ReDoc documentation
    POST /api/auth/*        - Autenticación (JWT)
    POST /api/extract       - Extracción de mensajes por período
    GET  /api/sync/status   - Estado de sincronización
    GET  /api/sync/periods  - Períodos extraídos
    POST /api/webhooks/*    - Webhooks de Chat2Desk (próximo)
    POST /api/analyze/*     - Análisis AI (próximo)
"""

import logging
from fastapi import FastAPI
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from datetime import datetime
import os

from app.config import get_settings
from app.database import check_db_connection
from app.logging_config import setup_logging
from app.routes.auth import router as auth_router
from app.routes.extraction import router as extraction_router

settings = get_settings()

# Configurar logging estructurado (JSON)
setup_logging(level="DEBUG" if settings.DEBUG else "INFO")
logger = logging.getLogger(__name__)

# Crear la aplicación FastAPI
app = FastAPI(
    title=settings.APP_NAME,
    version=settings.APP_VERSION,
    docs_url="/api/docs",      # Swagger UI
    redoc_url="/api/redoc"     # ReDoc documentation
)

# Archivos estáticos (CSS, JS, imágenes)
app.mount("/static", StaticFiles(directory="static"), name="static")

# Templates Jinja2 para renderizado server-side
templates = Jinja2Templates(directory="app/templates")

# Registrar rutas
app.include_router(auth_router)
app.include_router(extraction_router)


@app.get("/health")
async def health_check():
    """
    Health check del sistema.

    Verifica:
        - Estado de la API
        - Conexión a base de datos
        - Uptime del servicio

    Retorna:
        {
            "status": "healthy" | "degraded",
            "app": "API_C2D",
            "version": "1.0.0",
            "database": "connected" | "disconnected",
            "timestamp": "2026-07-20T..."
        }

    Nota: 'degraded' significa que la BD no responde pero la API funciona.
    """
    db_status = check_db_connection()
    status = "healthy" if db_status else "degraded"

    logger.info("health_check", extra={"db_status": status})

    return {
        "status": status,
        "app": settings.APP_NAME,
        "version": settings.APP_VERSION,
        "database": "connected" if db_status else "disconnected",
        "timestamp": datetime.utcnow().isoformat()
    }


@app.get("/")
async def root():
    """
    Endpoint raíz - Información básica de la API.

    Útil para verificar que la API está corriendo.
    Retorna nombre, versión y URL de documentación.
    """
    logger.info("root_request")
    return {
        "message": f"{settings.APP_NAME} API",
        "version": settings.APP_VERSION,
        "docs": "/api/docs"
    }
