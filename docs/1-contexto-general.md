# API_C2D - Contexto General del Proyecto

**Versión:** 2.0  
**Fecha:** 2026-07-15  
**Estado:** Para revisión

---

## 1. Visión General

API_C2D es una plataforma web multi-cliente que:
- Extrae conversaciones de Chat2Desk (vía API REST)
- Las analiza con IA (Google Gemini)
- Genera reportes ejecutivos accionables

**Objetivo:** Mejorar la conversión y eficiencia del flujo de atención al cliente.

---

## 2. Tipos de Reporte (Ejemplos)

El sistema es **genérico y configurable**. Cada usuario puede crear sus propios prompts de análisis según su industria y necesidades. A continuación se presentan 2 ejemplos de cómo se puede utilizar:

### 2.1 Ejemplo: Análisis de Campañas de Ads (Hisparep)
- **Industria:** Salud reproductiva (clínica de fertilidad)
- **Caso de uso:** Analizar conversaciones generadas por campañas de Facebook Ads
- **Pipeline:**
  - **Prompt 1:** Análisis por lead (27 columnas: nombre, campaña, fuente, intención, nivel de urgencia, etc.)
  - **Prompt 2:** Reporte ejecutivo con estrategia de neuroventa, secuencia de follow-up, y recomendaciones
- **Datos sensibles:** Contiene información de salud reproductiva (PHI)
- **Cumplimiento:** Ley Federal de Protección de Datos Personales (México)

### 2.2 Ejemplo: Análisis de Bot de Chat (Chat2Desk)
- **Industria:** Software de atención al cliente (SaaS)
- **Caso de uso:** Analizar eficiencia del bot de chat y flujo de atención
- **Pipeline:**
  - **Prompt único:** Análisis de flujo de bot (24 columnas: origen del lead, punto de abandono, eficiencia, etc.)
- **Datos:** No contiene PHI, pero tiene datos de negocio sensibles

> **Nota:** Estos son solo ejemplos. El sistema acepta cualquier token de Chat2Desk y permite configurar prompts personalizados por cliente/industria.

---

## 3. Arquitectura

### 3.1 Tipo
**Monolito modular** (no microservicios)

**Justificación:**
- Equipo pequeño (1 desarrollador)
- Timeline corto (2.5 semanas)
- Complejidad operativa innecesaria para MVP
- Fácil de desplegar y mantener

### 3.2 Stack Tecnológico

| Capa | Tecnología | Versión |
|------|------------|---------|
| Runtime | Python | 3.12 |
| Web Framework | FastAPI | 0.115+ |
| ASGI Server | Uvicorn | 0.34+ |
| Template Engine | Jinja2 | 3.1+ |
| Interactividad | HTMX | 2.0+ |
| CSS | Tailwind CSS | CDN |
| Base de Datos | MySQL | 8.0 |
| ORM/Driver | PyMySQL | 1.2+ |
| IA | Google Gemini | 2.0 Flash |
| PDF | xhtml2pdf | 0.2.15+ |
| Auth | JWT (python-jose) | 3.3+ |
| Proxy | Nginx | 1.24 |
| SSL | Let's Encrypt | Certbot |

### 3.3 Decisiones de Arquitectura (ADR)

| ADR | Decisión | Alternativa Rechazada | Razón |
|-----|----------|----------------------|-------|
| ADR-001 | Monolito modular | Microservicios | Timeline 2.5 semanas, 1 dev |
| ADR-002 | Gemini como IA principal | OpenAI, Claude | Costo, disponibilidad, rendimiento |
| ADR-003 | Token C2D como auth | OAuth, SSO | Simplicidad, ya existe el mecanismo |
| ADR-004 | MySQL 8.0 | PostgreSQL, ClickHouse | Ya instalado, suficiente para MVP |
| ADR-005 | Jinja2 + HTMX | React SPA | Sin build step, deploy simple |
| ADR-006 | BackgroundTasks | Celery/Redis | Sin dependencias adicionales |
| ADR-007 | xhtml2pdf | WeasyPrint | Más ligero, sin dependencias del sistema |
| ADR-008 | Multi-tenancy row-level | Schema-per-tenant | Simplicidad, 2 clientes en MVP |

---

## 4. Pipeline de Datos

```
Chat2Desk API → MySQL (mensajes_request)
                     │
                     ▼
        Limpieza de mensajes
        (filtrar system, autoreply si aplica)
                     │
                     ▼
        FastAPI BackgroundTask
                     │
                     ▼
        Gemini Prompt 1 (análisis)
                     │
                     ▼
        MySQL (analysis_jobs.prompt1_result)
                     │
                     ▼
        Gemini Prompt 2 (reporte)
                     │
                     ▼
        MySQL (analysis_jobs.prompt2_result)
                     │
                     ▼
        UI / PDF
```

### 4.1 Limpieza de Mensajes

Antes de enviar a Gemini, se filtran los mensajes irrelevantes:

| Tipo | ¿Se incluye? | Razón |
|------|---------------|-------|
| `from_client` | ✅ Sí | Mensajes del cliente |
| `to_client` | ✅ Sí | Mensajes del operador |
| `autoreply` | ⚠️ Configurable | Respuestas automáticas del bot (incluir si se analiza bot) |
| `system` | ❌ No | Asignaciones, tags, cierres (no relevantes para análisis) |
| `comment` | ⚠️ Configurable | Comentarios internos (generalmente excluir) |

> **Configuración:** El usuario puede decidir si incluir `autoreply` y `comment` según su caso de uso.

---

## 5. Modelo de Datos (MVP: 4 Tablas)

### 5.1 mensajes_request (EXISTENTE - 1,885 filas)
```sql
CREATE TABLE mensajes_request (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_id VARCHAR(50),
  dialog_id VARCHAR(50),
  mensaje_id VARCHAR(50),
  client_id VARCHAR(50),
  operator_id VARCHAR(50),
  tipo VARCHAR(20),           -- 'incoming' | 'outgoing'
  texto TEXT,
  transport VARCHAR(50),
  fecha_creacion DATETIME,
  INDEX idx_request (request_id),
  INDEX idx_dialog (dialog_id),
  INDEX idx_client (client_id)
);
```

### 5.2 analysis_jobs (NUEVA)
```sql
CREATE TABLE analysis_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  request_id VARCHAR(50),
  month INT,
  year INT,
  status ENUM('pending', 'running', 'completed', 'error') DEFAULT 'pending',
  prompt1_result JSON,        -- Análisis por lead (Prompt 1)
  prompt2_result JSON,        -- Reporte ejecutivo (Prompt 2)
  gemini_tokens_used INT DEFAULT 0,
  error_message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  started_at TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  INDEX idx_client_month (client_id, year, month),
  INDEX idx_status (status)
);
```

### 5.3 clients (NUEVA)
```sql
CREATE TABLE clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  industry VARCHAR(50),
  c2d_token VARCHAR(255),     -- Token de Chat2Desk por cliente
  config JSON,                -- Configuración flexible
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 5.4 extracted_periods (NUEVA)
```sql
CREATE TABLE extracted_periods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  year INT NOT NULL,
  month INT NOT NULL,
  total_dialogs INT DEFAULT 0,
  total_messages INT DEFAULT 0,
  status ENUM('pending', 'extracting', 'completed', 'error') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  UNIQUE KEY unique_period (client_id, year, month)
);
```

**¿Por qué 4 tablas?**
- MVP con 2 clientes y ~2,000 filas
- Tablas mínimas para funcionar: datos, análisis, clientes, control de períodos
- Las demás tablas se agregan cuando haya queries concretas que las requieran
- Menos tablas = menos JOINs = menos bugs = más rápido de desarrollar

---

## 6. Autenticación y Autorización

### 6.1 Flujo de Login

El sistema utiliza un **login existente** (proveniente de otro repo) para la autenticación de usuarios:

```
1. Usuario ingresa credenciales (email/usuario + contraseña)
2. Backend valida contra el sistema de autenticación existente
3. Si es válido → genera JWT
4. JWT se guarda en cookie httpOnly
5. Requests subsecuentes validan JWT
```

### 6.2 Token de Chat2Desk (Campo de Configuración)

El token de Chat2Desk **NO se usa para autenticación**. Es un campo de configuración que cada usuario ingresa para:
- Conectar su cuenta de Chat2Desk
- Extraer conversaciones de esa cuenta
- Se almacena en la tabla `clients` o en configuración del usuario

```
Usuario configura:
├── Token de Chat2Desk (para extraer datos)
├── API Key de Gemini (para análisis)
└── Prompts personalizados (opcional)
```

### 6.3 Roles

Los roles se heredan del sistema de autenticación existente:
- `administrator` → Acceso completo
- `supervisor` → Acceso completo
- `operator` → Acceso limitado (según configure el admin)

### 6.4 JWT Config
- Expiración: 8 horas
- Almacenamiento: Cookie httpOnly + header Authorization
- Secret: variable de entorno `JWT_SECRET`

---

## 7. Control de Sincronización

### 7.1 Extracción por Período
- Usuario selecciona: **mes + año** (ej: "Enero 2026")
- Backend busca todos los dialogs con mensajes en ese período
- Verifica en `extracted_periods` si ya se extrajo ese período
- Si existe y status='completed' → notifica "ya extrajo este período"
- Si no existe → crea registro y comienza extracción
- Al terminar → actualiza status y totales

### 7.2 Tabla de Control de Períodos

```sql
CREATE TABLE extracted_periods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  year INT NOT NULL,
  month INT NOT NULL,
  total_dialogs INT DEFAULT 0,
  total_messages INT DEFAULT 0,
  status ENUM('pending', 'extracting', 'completed', 'error') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  UNIQUE KEY unique_period (client_id, year, month)
);
```

### 7.3 Deduplicación
- **Mensajes:** UNIQUE constraint en `mensaje_id` + INSERT IGNORE
- **Períodos:** UNIQUE constraint en (client_id, year, month)

### 7.4 Extracción en Tiempo Real (Post-MVP)
- Webhooks de Chat2Desk: inbox, outbox, imported_message, add_tag_to_request
- **NO está en MVP** - se agrega en fase 2

---

## 8. Manejo de Errores

### 8.1 Chat2Desk API
```python
# Exponential backoff: 5s → 15s → 45s
# Máximo 3 reintentos
# Timeout: 15 segundos por request (ajustable a 30s para requests pesados)
```

### 8.2 Gemini API
```python
# Reintentos: 2 máximo
# Timeout: 60 segundos
# Rate limit: espera 60s si recibe 429
```

### 8.3 MySQL
```python
# Batch commits cada 500 filas
# Rollback en error de batch
# Connection pool: 5 conexiones máximo
```

### 8.4 Frontend
```python
# Errores 4xx → mensaje amigable en UI
# Errores 5xx → log + mensaje genérico
# Todos los errores → logging estructurado (JSON)
```

---

## 9. Seguridad

### 9.1 Datos en Tránsito
- HTTPS forzado (Nginx)
- TLS 1.2+

### 9.2 Datos en Reposo
- MySQL: sin encriptación en disco (MVP)
- Post-MVP: considerar encriptación para datos de salud

### 9.3 Secrets
- **NUNCA** en código fuente
- Almacenados en `.env`
- `.env` excluido de `.gitignore`

### 9.4 Datos de Salud (Hisparep)
- Clínica de fertilidad → información de salud reproductiva
- **PHI (Protected Health Information)**
- **Acciones MVP:** logging de acceso, política de retención
- **Acciones post-MVP:** encriptación en reposo, auditoría, DPA con proveedores

---

## 10. Monitoreo Básico

| Componente | Método | Frecuencia |
|------------|--------|------------|
| API Health | `GET /health` | On-demand |
| MySQL | Query `SELECT 1` | En health check |
| Gemini | Test call simple | En health check |
| Extracción | sync_log status | Post-extracción |
| Errores | Logging estructurado | Continuo |

---

## 11. Glossary

| Término | Definición |
|---------|------------|
| **C2D** | Chat2Desk - plataforma de mensajería unificada |
| **Request** | Conversación/caso de atención en C2D |
| **Dialog** | Mensaje individual dentro de un request |
| **Prompt 1** | Análisis por lead/conversación (salida tabular) |
| **Prompt 2** | Reporte ejecutivo con estrategia (salida narrativa) |
| **PHI** | Protected Health Information - datos de salud protegidos |
| **Multi-tenancy** | Arquitectura donde múltiples clientes comparten una instancia |
| **BackgroundTask** | Tarea asíncrona ejecutada por FastAPI sin bloquear la respuesta |

---

## 12. Preguntas Abiertas

1. **¿Cuántos clientes tendrá en producción?** (MVP = 2, pero escalar a 10+?)
2. **¿Los prompts de Hisparep son fijos o cambian por campaña?**
3. **¿Necesitan exportar a Excel además de PDF?**
4. **¿Qué pasa si Gemini no puede procesar una conversación?** (fallback manual?)
5. **¿Política de retención de datos?** (¿cuánto tiempo se guardan las conversaciones?)
