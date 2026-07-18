# API_C2D - MVP (Producto Mínimo Viable)

**Versión:** 2.0  
**Fecha:** 2026-07-15  
**Presupuesto:** 2.5 semanas (12 días hábiles)  
**Estado:** Para revisión

---

## MÓDULO 1: FUNDAMENTOS (Días 1-2)

- [ ] Setup: FastAPI + estructura + .env + config.py
- [ ] MySQL: mantener mensajes_request existente + crear analysis_jobs + clients
- [ ] Health check: `GET /health`
- [ ] Logging estructurado (JSON)
- [ ] Git init + `.gitignore`
- [ ] `requirements.txt` con todas las dependencias

---

## MÓDULO 2: AUTH (Día 3)

- [ ] Integrar login existente (del repo que nos dieron)
- [ ] JWT (expiración 8h)
- [ ] Middleware de protección de rutas
- [ ] Configuración de token C2D (campo en UI para que el usuario lo ingrese)
- [ ] Login page (HTML + Tailwind)

---

## MÓDULO 3: EXTRACCIÓN (Días 4-5)

- [ ] Refactor scripts → servicio `chat2desk.py`
- [ ] Exponential backoff (3 reintentos: 5s → 15s → 45s)
- [ ] sync_log (control por cliente/mes)
- [ ] Paginación robusta (offset循环 con deduplicación)
- [ ] Batch commits cada 500 filas
- [ ] Endpoints: `POST /api/extract`, `GET /api/sync/status`

---

## MÓDULO 4: UI (Días 5-7)

- [ ] Login page
- [ ] Dashboard (resumen de chats, análisis recientes)
- [ ] Chats: lista + filtros (por fecha, estado, cliente)
- [ ] Chat detail: timeline de mensajes
- [ ] Selector de mes para extracción
- [ ] HTMX para interactividad parcial (sin recargar página)
- [ ] Tailwind CSS via CDN

---

## MÓDULO 5: ANÁLISIS IA (Días 7-9)

- [ ] AI Engine: Gemini adapter
- [ ] BackgroundTasks para pipeline async
- [ ] Prompt 1: messages → `analysis_jobs.prompt1_result`
- [ ] Prompt 2: prompt1_result → `analysis_jobs.prompt2_result`
- [ ] Contador de tokens
- [ ] UI: `/analysis` con resultados
- [ ] Manejo de errores (rate limit, timeout, fallback)

---

## MÓDULO 6: REPORTES (Día 10)

- [ ] Generación PDF con xhtml2pdf
- [ ] Descarga desde UI
- [ ] Templates HTML para PDF
- [ ] Formato profesional (logo, headers, tablas)

---

## MÓDULO 7: DEPLOY (Días 11-12)

- [ ] Nginx HTTPS (ya configurado)
- [ ] Supervisor para Uvicorn
- [ ] Tests básicos (health check, login, extracción)
- [ ] Documentación (README.md)
- [ ] `.env.example` para nuevos desarrolladores

---

## Criterios de Aceptación

1. ✅ Login con token C2D funciona
2. ✅ Extracción de chats por mes sin duplicados
3. ✅ Timeline de mensajes visible
4. ✅ Análisis con Gemini produce resultados
5. ✅ Reporte PDF descargable
6. ✅ Todo bajo HTTPS
7. ✅ Errores manejados (no crashes silenciosos)

---

## ¿Qué NO está en MVP?

- ClickHouse
- Múltiples proveedores de IA
- Edición de prompts desde UI
- Sync en tiempo real (webhooks)
- Dashboard analytics con gráficas
- Export DOCX
- Docker
- CI/CD
- Tests unitarios completos
- Documentación de API (Swagger)

---

## Estructura de Directorios

```
API_C2D/
├── app/
│   ├── __init__.py
│   ├── main.py              # FastAPI app
│   ├── config.py            # Settings from .env
│   ├── database.py          # MySQL connection pool
│   ├── models/
│   │   ├── __init__.py
│   │   └── schemas.py       # Pydantic models
│   ├── routes/
│   │   ├── __init__.py
│   │   ├── auth.py          # Login, JWT
│   │   ├── chats.py         # Chat list, timeline
│   │   ├── extraction.py    # Sync endpoints
│   │   ├── analysis.py      # AI analysis
│   │   └── reports.py       # PDF generation
│   ├── services/
│   │   ├── __init__.py
│   │   ├── chat2desk.py     # C2D API client
│   │   ├── gemini.py        # Gemini API client
│   │   └── pdf.py           # PDF generator
│   ├── prompts/
│   │   ├── __init__.py
│   │   ├── ads_analysis.py   # Prompts para análisis de campañas de Ads
│   │   └── bot_analysis.py   # Prompts para análisis de comportamiento del bot
│   └── templates/
│       ├── base.html
│       ├── login.html
│       ├── dashboard.html
│       ├── chats.html
│       ├── chat_detail.html
│       ├── analysis.html
│       └── reports.html
├── static/
│   ├── css/
│   └── js/
├── .env.example
├── .gitignore
├── requirements.txt
└── README.md
```

---

## Dependencias Python

```
fastapi>=0.115.0
uvicorn[standard]>=0.34.0
pymysql>=1.2.0
requests>=2.34.0
python-jose[cryptography]>=3.3.0
python-multipart>=0.0.18
jinja2>=3.1.0
google-generativeai>=0.8.0
xhtml2pdf>=0.2.15
pydantic>=2.0.0
pydantic-settings>=2.0.0
```

---

## Timeline Visual

```
Semana 1:
├── Lunes:    Módulo 1 (Fundamentos)
├── Martes:   Módulo 1 (Fundamentos)
├── Miércoles: Módulo 2 (Auth)
├── Jueves:   Módulo 3 (Extracción)
└── Viernes:  Módulo 3 (Extracción) + Inicio Módulo 4 (UI)

Semana 2:
├── Lunes:    Módulo 4 (UI)
├── Martes:   Módulo 4 (UI)
├── Miércoles: Módulo 5 (Análisis IA)
├── Jueves:   Módulo 5 (Análisis IA)
└── Viernes:  Módulo 6 (Reportes)

Semana 3 (2 días):
├── Lunes:    Módulo 7 (Deploy)
└── Martes:   Módulo 7 (Deploy) + Testing final
```
