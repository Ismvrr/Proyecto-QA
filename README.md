# API_C2D - Plataforma de Análisis de Conversaciones Chat2Desk

Sistema que extrae conversaciones de Chat2Desk, las analiza con IA (Gemini) y genera reportes ejecutivos.

## Stack

| Componente | Tecnología |
|------------|------------|
| Auth | Laravel (PHP 8.3) |
| API | FastAPI (Python 3.12) |
| Proxy | Nginx |
| BD | MySQL 8.0 (remoto) |

## Instalación

```bash
# Clonar repo
git clone <repo-url>
cd ProyectoQA

# FastAPI
cd fastapi
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
cp .env.example .env  # Configurar credenciales

# Ejecutar
uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
```

## Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/health` | Health check |
| GET | `/api/docs` | Swagger UI |

## Configuración

Las variables de entorno se configuran en `fastapi/.env`:

```env
# BD Remota
DB_HOST=10.0.0.92
DB_PORT=3306
DB_USER=dev_ismael
DB_PASS=***
DB_NAME=db_c2dqaia

# Gemini (pendiente)
GEMINI_API_KEY=

# JWT
JWT_SECRET=***
```

## Estructura

```
ProyectoQA/
├── fastapi/          # Backend Python
│   ├── app/
│   │   ├── main.py
│   │   ├── config.py
│   │   ├── database.py
│   │   ├── models/
│   │   ├── routes/
│   │   ├── services/
│   │   ├── prompts/
│   │   └── templates/
│   ├── static/
│   ├── .env
│   └── requirements.txt
├── laravel/          # Auth (PHP)
├── nginx/            # Config proxy
└── README.md
```
