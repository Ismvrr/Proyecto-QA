# C2D OnCloud Suite (C2D-IW)Wrapper inteligente y ecosistema de herramientas de gestión multitenant con acceso tipo funnel integrado a Chat2Desk.

## 📌 Identidad del Proyecto
- **Namespace Local:** `c2d-oncloud-suite`
- **Versión Actual:** `v0.0.1-init`
- **Estado:** Sprint 1 - Configuración de Entorno

Sistema de gestión multitenant con acceso tipo funnel integrado a Chat2Desk.

## 🚀 Plan de Implementación
- **Sprint 1:** Auth Nivel 1 & Iframe (En curso)
- **Sprint 2:** Sync de Compañías & API Tokens
- **Sprint 3:** RBAC & Menús Dinámicos
- **Sprint 4:** Automatización & Refresco de Operadores

## 🛠  Requisitos
- PHP 8.2+
- Composer
- Node.js & NPM

## 🛠️ Instalación de Base de Datos
1. Configurar `.env` con credenciales de MariaDB.
2. Ejecutar `php artisan migrate`.
*Nota: Se eliminó la migración por defecto de Laravel para dar prioridad a la tabla de la Suite.*

## ⚙️ Configuración Técnica
- **Services:** La lógica de comunicación con la API de C2D reside en `app/Services/Chat2DeskService.php`.
- **Seguridad:** Los tokens de API de las empresas se almacenan encriptados mediante la clave `APP_KEY` de Laravel.
- **Modelos:** - `User`: Maneja la autenticación híbrida y almacena el `auth_key` dinámico.
  - `Company`: Gestiona el acceso Nivel 2 mediante el API Token.
