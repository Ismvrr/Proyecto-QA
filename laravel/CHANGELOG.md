# Change Log C2D OnCloud Suite (c2d-oncloud-suite)
## [0.2.0-alpha.1] - 2026-04-09
### Added
- Dashboard SPA (Single Page Application) usando Alpine.js y Tailwind CSS sin recarga de página.
- Sidebar jerárquico tipo acordeón (Padre/Hijo) para navegación escalable.
- Integración de Chart.js para gráficas de dona en tiempo real de roles de usuarios.
- Modal inteligente de conexión que detecta si el token de la empresa ya está sincronizado.
- Estructura UI base para futuras integraciones Multi-App (Ej. Issabel PBX).

### Changed
- Refactor visual completo del Dashboard aplicando guía de marca (Fuentes Nunito/Open Sans, colores `#0A3656`, `#CDD8E6`).
- El comando `c2d:sync-operators` ahora realiza limpieza automática, deshabilitando usuarios que ya no existen en la API de Chat2Desk.
- La consulta de `$stats` en el dashboard ahora filtra exclusívamente a los usuarios activos (`status='enabled'`) y excluye al rol `shadow`.

### Fixed
- Resuelto error de integridad `Duplicate entry for key c2d_user_id_unique` en la sincronización, cambiando la llave de búsqueda de `email` a `c2d_user_id` en el método `updateOrCreate`.

## [0.1.0-alpha.1] - 2026-03-18
### Added
- Estructura de base de datos final para `companies` y `users`.
- Implementación de borrado lógico (`isdeleted`) en tablas principales.
- Configuración de conexión exitosa a MariaDB.
### Fixed
- Eliminada migración de usuarios por defecto de Laravel para evitar conflictos con la Suite.
## [0.1.0-alpha.2] - 2026-03-18
### Added
- Modelos Eloquent: `User.php` y `Company.php` con relaciones `hasMany/belongsTo`.
- `Chat2DeskService`: Implementación inicial del método `signIn` para consumo de API externa.
- Soporte para encriptación nativa de `api_token` en el modelo `Company`.

### Changed
- Configuración de `services.php` para centralizar URLs de Chat2Desk desde el `.env`.
- Modelo `User` extendido para soportar campos específicos de C2D (`auth_key`, `c2d_user_id`, etc.).
