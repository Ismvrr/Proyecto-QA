"""
Database connection module for MySQL
Uses PyMySQL with connection pooling
"""

import pymysql
from pymysql.cursors import DictCursor
from contextlib import contextmanager
from app.config import get_settings

settings = get_settings()


def get_connection():
    """Get a single database connection"""
    return pymysql.connect(
        host=settings.DB_HOST,
        port=settings.DB_PORT,
        user=settings.DB_USER,
        password=settings.DB_PASS,
        database=settings.DB_NAME,
        charset='utf8mb4',
        cursorclass=DictCursor,
        autocommit=True
    )


@contextmanager
def get_db():
    """Context manager for database connections"""
    conn = get_connection()
    try:
        yield conn
    finally:
        conn.close()


def check_db_connection():
    """Check if database is accessible"""
    try:
        with get_db() as conn:
            with conn.cursor() as cursor:
                cursor.execute("SELECT 1")
                result = cursor.fetchone()
                return result is not None
    except Exception as e:
        print(f"Database connection error: {e}")
        return False
