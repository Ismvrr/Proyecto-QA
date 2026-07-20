"""
API_C2D - Main FastAPI Application
Platform for analyzing Chat2Desk conversations with AI
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

settings = get_settings()

# Configure structured logging
setup_logging(level="DEBUG" if settings.DEBUG else "INFO")
logger = logging.getLogger(__name__)

# Create FastAPI app
app = FastAPI(
    title=settings.APP_NAME,
    version=settings.APP_VERSION,
    docs_url="/api/docs",
    redoc_url="/api/redoc"
)

# Mount static files
app.mount("/static", StaticFiles(directory="static"), name="static")

# Templates
templates = Jinja2Templates(directory="app/templates")


@app.get("/health")
async def health_check():
    """
    Health check endpoint
    Returns status of API, database, and uptime
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
    """Root endpoint - redirects to dashboard"""
    logger.info("root_request")
    return {
        "message": f"{settings.APP_NAME} API",
        "version": settings.APP_VERSION,
        "docs": "/api/docs"
    }
