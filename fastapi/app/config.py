"""
Configuration settings for API_C2D
Loads variables from .env file
"""

from pydantic_settings import BaseSettings
from functools import lru_cache


class Settings(BaseSettings):
    # App
    APP_NAME: str = "API_C2D"
    APP_VERSION: str = "1.0.0"
    DEBUG: bool = False

    # MySQL (Remote)
    DB_HOST: str = ""
    DB_PORT: int = 3306
    DB_USER: str = ""
    DB_PASS: str = ""
    DB_NAME: str = ""

    # Chat2Desk
    C2D_BASE_URL: str = "https://api.chat2desk.com.mx/v1"
    C2D_WEB_URL: str = "https://web.chat2desk.com.mx"
    C2D_DEFAULT_TOKEN: str = ""

    # Gemini
    GEMINI_API_KEY: str = ""
    GEMINI_MODEL: str = "gemini-2.0-flash"

    # JWT
    JWT_SECRET: str = "change-this-in-production"
    JWT_ALGORITHM: str = "HS256"
    JWT_EXPIRY_MINUTES: int = 480

    # Server
    HOST: str = "0.0.0.0"
    PORT: int = 8000

    class Config:
        env_file = ".env"
        env_file_encoding = "utf-8"


@lru_cache()
def get_settings() -> Settings:
    return Settings()
