# API_C2D - Requisitos para Montaje en Producción

**Fecha:** 2026-07-15

---

## 1. Requisitos del Servidor

### 1.1 Hardware

| Recurso | Mínimo | Recomendado |
|---------|--------|-------------|
| CPU | 2 cores | 4 cores |
| RAM | 4 GB | 8 GB |
| Disco | 40 GB SSD | 80 GB SSD |
| OS | Ubuntu 22.04+ | Ubuntu 24.04 |
| Conexión | 100 Mbps | 1 Gbps |

> **Nota:** Mario proporciona el servidor con estas especificaciones.

### 1.2 Software (pre-instalado en el servidor)

| Software | Versión | Propósito |
|----------|---------|-----------|
| Python | 3.10+ | Lenguaje de la aplicación |
| MySQL | 8.0+ | Base de datos |
| Nginx | 1.18+ | Proxy inverso + HTTPS |
| Certbot | Latest | Certificados SSL gratuitos |
| Supervisor | Latest | Mantener la app corriendo |

> **Nota:** Mario instala y configura este software, o el desarrollador lo instala si tiene acceso sudo.

---

## 2. Requisitos de Red

### 2.1 Puertos

| Puerto | Dirección | Propósito |
|--------|-----------|-----------|
| 443 | Entrante | HTTPS (acceso de usuarios) |
| 80 | Entrante | Redirect automático a HTTPS |
| 22 | Entrante | SSH (solo IP del desarrollador) |

> **Nota:** Mario configura el firewall y abre estos puertos.

### 2.2 Dominio con HTTPS

Se necesita un dominio con certificado SSL para acceder al sistema.

**Opciones:**

| Opción | Descripción | Costo |
|--------|-------------|-------|
| A | Mario provee un subdominio (ej: `api.empresa.com`) | $0 |
| B | El desarrollador crea un dominio nuevo | ~$10-15/año |

> **Nota:** Mario decide la opción. Si es opción B, el desarrollador registra el dominio y configura DNS.

---

## 3. Credenciales Necesarias

### 3.1 API Key de Google Gemini

- **Para qué:** Análisis de conversaciones con IA
- **Costo:** ~$5-20/mes (pago por uso)
- **Cómo obtener:** [Google Cloud Console](https://console.cloud.google.com/) → APIs → Gemini API → Create credentials
- **La solicita:** El desarrollador con tarjeta de crédito de la empresa

### 3.2 Token de Chat2Desk

- **Para qué:** Extraer conversaciones de la plataforma
- **Costo:** $0 (incluido en suscripción actual)
- **Cómo obtener:** Chat2Desk → Configuración → API → Token
- **Lo provee:** Mario desde el panel de administración

### 3.3 Acceso SSH al servidor

- **Para qué:** Instalar y configurar la aplicación
- **Nivel:** root o usuario con sudo
- **Lo provee:** Mario

### 3.4 Acceso a la base de datos

- **Para qué:** Crear tablas y usuario de la aplicación
- **Credenciales:** Usuario y contraseña de MySQL root
- **Lo provee:** Mario (o el desarrollador si tiene acceso sudo)

---

## 4. Configuración de la Aplicación

### 4.1 Variables de Entorno

El sistema necesita las siguientes variables configuradas en un archivo `.env`:

| Variable | Propósito | Valor |
|----------|-----------|-------|
| `GEMINI_API_KEY` | API Key de Google Gemini | La que proporcione la empresa |
| `C2D_DEFAULT_TOKEN` | Token de Chat2Desk | El que provea Mario |
| `DB_HOST` | Host de MySQL | `localhost` (o IP del servidor MySQL) |
| `DB_USER` | Usuario de MySQL | `chat2desk` |
| `DB_PASS` | Contraseña de MySQL | La que se defina |
| `DB_NAME` | Nombre de la base de datos | `chat2desk` |
| `JWT_SECRET` | Secreto para JWT | Se genera aleatoriamente |
| `SECRET_KEY` | Secreto de la app | Se genera aleatoriamente |

### 4.2 Base de Datos

El sistema necesita 3 tablas:

| Tabla | Propósito |
|-------|-----------|
| `mensajes_request` | Almacena mensajes extraídos de Chat2Desk |
| `analysis_jobs` | Almacena resultados de análisis y reportes |
| `clients` | Almacena información de clientes configurados |

> **Nota:** El desarrollador crea estas tablas después de tener acceso a MySQL.

---

## 5. Costos

| Concepto | Costo Mensual | Notas |
|----------|---------------|-------|
| Servidor | $0 | Ya existe |
| SSL | $0 | Let's Encrypt |
| Gemini API | ~$5-20 | Pago por uso |
| MySQL | $0 | Incluido en servidor |
| Dominio | ~$1/mes | Solo si se crea nuevo |
| **Total** | **~$6-21/mes** | |

---

## 6. Resumen de Acciones

| Acción | Responsable | Estado |
|--------|-------------|--------|
| Proporcionar servidor con specs | Mario | Pendiente |
| Dar acceso SSH | Mario | Pendiente |
| Proveer token C2D | Mario | Pendiente |
| Configurar dominio/SSL | Mario o desarrollador | Pendiente |
| Solicitar API Key Gemini | Desarrollador | Pendiente |
| Instalar software en servidor | Desarrollador | Pendiente |
| Configurar base de datos | Desarrollador | Pendiente |
| Instalar y configurar app | Desarrollador | Pendiente |
