# API_C2D

Plataforma multiempresa para extraer conversaciones de Chat2Desk, analizarlas manualmente con Gemini y generar reportes PDF.

## Alcance V1

Incluido:

- Login y sesión protegida por Laravel.
- Extracción manual de conversaciones por año y mes.
- Conversaciones agrupadas por `dialog_id` y `request_id`.
- Timeline y filtros de mensajes.
- Prompts base y prompts personalizados por empresa.
- Análisis manual individual y mensual con Gemini.
- Consolidación mensual de resultados.
- Historial de análisis con tokens y resultados.
- Reportes PDF mensuales e históricos.
- BYOK, sin persistir la API key del usuario.
- HTTPS mediante Nginx.

Fuera de V1:

- Análisis automático durante la extracción.
- Cron jobs.
- Análisis iniciado por webhooks.
- Activación operativa de webhooks por cliente.
- Dashboard analítico con gráficas.

## Arquitectura

```text
Internet
   |
   v
Nginx + HTTPS:443
   |------------------------------|
   v                              v
Laravel :8080                 FastAPI :8000
Auth, UI, proxy               C2D, Gemini, PDF
   |                              |
   |-------------- MySQL ----------|
```

Componentes:

| Componente | Tecnología | Operación |
|------------|------------|-----------|
| Interfaz y autenticación | Laravel 13, PHP 8.4 | `api-c2d-laravel.service` |
| API y análisis | FastAPI, Python 3.12 | `api-c2d-fastapi.service` |
| Proxy y HTTPS | Nginx, Let's Encrypt | Servicio del sistema |
| Base de datos | MySQL 8 remoto | Servicio externo |
| IA | Google Gemini, SDK `google-genai` | Peticiones manuales |
| PDF | `xhtml2pdf` | Generación desde resultados guardados |

Proxmox puede supervisar `GET /health`, pero la operación de Laravel y FastAPI ocurre dentro de la VM mediante systemd.

## Estructura

```text
ProyectoQA/
├── fastapi/
│   ├── app/
│   │   ├── routes/
│   │   │   ├── analysis.py
│   │   │   ├── extraction.py
│   │   │   ├── reports.py
│   │   │   └── webhooks.py
│   │   ├── services/
│   │   │   ├── chat2desk.py
│   │   │   ├── gemini.py
│   │   │   └── reports.py
│   │   ├── templates/
│   │   │   └── monthly_report.html
│   │   ├── config.py
│   │   └── main.py
│   ├── .env.example
│   ├── api-c2d-fastapi.service
│   └── requirements.txt
├── laravel/
│   ├── app/
│   ├── database/migrations/
│   ├── resources/views/
│   ├── api-c2d-laravel.service
│   └── routes/
├── docs/
└── README.md
```

## Instalación local

### FastAPI

```bash
cd ProyectoQA/fastapi
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
```

Configura `.env` con valores del entorno y ejecuta:

```bash
uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
```

### Laravel

En otra terminal:

```bash
cd ProyectoQA/laravel
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=0.0.0.0 --port=8080
```

Los archivos `.env` reales nunca deben subirse a Git. Usa únicamente los archivos `.env.example` como referencia.

## Configuración

`fastapi/.env.example` contiene placeholders para:

```env
DB_HOST=your_db_host
DB_PORT=3306
DB_USER=your_db_user
DB_PASS=your_db_password
DB_NAME=your_db_name

C2D_BASE_URL=https://api.chat2desk.com.mx/v1
C2D_WEB_URL=https://web.chat2desk.com.mx
C2D_DEFAULT_TOKEN=

GEMINI_API_KEY=
GEMINI_MODEL=gemini-3.5-flash-lite
GEMINI_ENABLED=false

JWT_SECRET=CHANGE_THIS_IN_PRODUCTION
JWT_ALGORITHM=HS256
JWT_EXPIRY_MINUTES=480
```

Reglas de Gemini:

- `GEMINI_API_KEY` es la Server Key opcional del entorno.
- BYOK se recibe durante la petición y no se guarda en la base de datos.
- BYOK no se incluye en prompts, URLs, snapshots ni respuestas.
- Server Key tiene límites de 2 análisis por minuto y 30 por día por empresa.
- El análisis solo se ejecuta cuando el usuario lo solicita.

## Operación en servidor

Instala las unidades systemd una sola vez:

```bash
sudo cp fastapi/api-c2d-fastapi.service /etc/systemd/system/
sudo cp laravel/api-c2d-laravel.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now api-c2d-fastapi
sudo systemctl enable --now api-c2d-laravel
```

Estado:

```bash
systemctl status api-c2d-fastapi
systemctl status api-c2d-laravel
systemctl is-enabled api-c2d-fastapi api-c2d-laravel
systemctl is-active api-c2d-fastapi api-c2d-laravel
```

Reinicio individual:

```bash
sudo systemctl restart api-c2d-fastapi
sudo systemctl restart api-c2d-laravel
```

Logs:

```bash
journalctl -u api-c2d-fastapi -f
journalctl -u api-c2d-laravel -f
```

No es necesario reiniciar toda la VM para aplicar cambios a un servicio. Un reinicio completo solo prueba que las unidades habilitadas arranquen automáticamente.

## Rutas principales

Rutas públicas detrás de Nginx:

| Método | Ruta | Propósito |
|--------|------|-----------|
| GET | `/health` | Health check de FastAPI y conexión MySQL |
| GET | `/login` | Login Laravel |
| GET | `/dashboard` | Interfaz principal |
| GET | `/api/docs` | Swagger de FastAPI |
| GET | `/reports/monthly` | Descargar PDF mensual |
| GET | `/reports/analysis/{jobId}` | Descargar PDF histórico |

Rutas FastAPI protegidas:

| Método | Ruta | Propósito |
|--------|------|-----------|
| POST | `/api/extract` | Extracción manual por periodo |
| POST | `/api/analyze/conversation` | Análisis de una conversación |
| POST | `/api/analyze/period` | Análisis por periodo y consolidación opcional |
| GET | `/api/reports/monthly` | Generación PDF interna |
| GET | `/api/reports/job/{job_id}` | PDF de un job histórico |

## Flujo de análisis y PDF

```text
Usuario selecciona periodo y prompt
              |
              v
Extracción manual -> mensajes_request
              |
              v
Gemini analiza conversaciones completas
              |
              v
analysis_jobs.prompt1_result
              |
              v
Consolidación mensual
              |
              v
analysis_jobs.prompt2_result
              |
              v
PDF generado desde MySQL
```

La generación del PDF no vuelve a llamar a Gemini. El reporte usa los resultados almacenados después de la última extracción del periodo para evitar mezclar análisis antiguos.

## Pruebas rápidas

Desde la VM:

```bash
curl http://localhost:8000/health
curl http://localhost:8080/login
```

Desde una máquina externa:

```bash
curl.exe -i https://2fa.chat2desk.support/health
curl.exe -i https://2fa.chat2desk.support/login
```

La respuesta esperada de `/health` incluye:

```json
{
  "status": "ok"
}
```

Para una prueba de análisis se recomienda comenzar con una sola conversación. Para probar consolidación se deben seleccionar al menos dos conversaciones y usar BYOK.

## Troubleshooting

### FastAPI no responde

```bash
systemctl status api-c2d-fastapi
journalctl -u api-c2d-fastapi -n 100 --no-pager
curl http://localhost:8000/health
```

### Laravel no responde

```bash
systemctl status api-c2d-laravel
journalctl -u api-c2d-laravel -n 100 --no-pager
curl http://localhost:8080/login
```

### El dominio no responde desde la VM

La VM puede no soportar NAT loopback hacia su propia IP pública. Prueba desde una máquina externa o valida Nginx localmente:

```bash
curl -k --resolve 2fa.chat2desk.support:443:127.0.0.1 https://2fa.chat2desk.support/health
```

### Gemini devuelve `404`

Revisa que el modelo configurado esté vigente y disponible para la API key. El modelo de referencia actual es:

```env
GEMINI_MODEL=gemini-3.5-flash-lite
```

### Gemini devuelve `429`

Revisa Billing, cuota y límites del proyecto. Para pruebas controladas puedes usar BYOK.

## Seguridad

- No subir `.env`, API keys, tokens, contraseñas ni cookies de sesión.
- No colocar API keys dentro de prompts o URLs.
- Mantener HTTPS activo en producción.
- Mantener BYOK fuera de la base de datos.
- Validar el `company_id` en todas las consultas protegidas.
- No exponer logs con headers de autenticación.

## Autoría

Creado y mantenido por **Ismael Ramírez**.
