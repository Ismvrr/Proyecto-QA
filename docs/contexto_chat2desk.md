# Contexto completo - Extracción de mensajes Chat2Desk

## 1. Resumen del proyecto

Se desarrollaron scripts en Python para extraer mensajes de la API REST de Chat2Desk (México).
El proyecto inició con un script que descarga **todos** los chats y luego se creó otro para
extraer mensajes de un **RequestID específico** (una sesión única de chat por cliente).

---

## 2. Archivos del proyecto

### 2.1 `Extraer chat de dialogos.py`
- **Propósito:** Descarga masivamente todos los chats desde Chat2Desk y los guarda en un CSV
- **Comportamiento:**
  - Lee un CSV existente (`historial_ordenado_sin_system.csv`) para evitar duplicados
  - Obtiene la lista completa de diálogos via `GET /dialogs`
  - Para cada diálogo nuevo, extrae todos los mensajes via `GET /messages?dialog_id=X`
  - Filtra mensajes tipo `system`
  - Guarda en CSV con columnas: Chat_ID, Mensaje_ID, Fecha, Tipo, Mensaje
- **No modificado** - se mantiene como respaldo original

### 2.2 `Extraer_por_request.py`
- **Propósito:** Extraer mensajes de un **RequestID específico** (solicitud/sesión única)
- **Parámetros de configuración** (líneas 13-16 del archivo):
  - `REQUEST_ID` - número del request (sin prefijo N-)
  - `DIALOG_ID` - ID del diálogo (opcional, acelera búsqueda)
  - `DATE_FROM` / `DATE_TO` - rango de fechas (alternativa rápida)
- **Parámetros por línea de comandos** (tienen prioridad sobre las variables del archivo):
  - `--request-id` (obligatorio si no está en variables)
  - `--dialog-id` (opcional)
  - `--date-from` / `--date-to` (opcional)
- **3 estrategias de búsqueda** en orden de prioridad:
  1. Por `dialog_id` (más rápido)
  2. Por rango de fechas (`start_date` / `finish_date`)
  3. Iterar todos los diálogos (lento, último recurso)
- **Output:** CSV con columnas: Dialog_ID, Mensaje_ID, Fecha, Tipo, Mensaje, Request_ID, Client_ID, Operator_ID, Transport

---

## 3. Investigación API Chat2Desk

### 3.1 Configuración
- **Base URL:** `https://api.chat2desk.com.mx/v1`
- **Autenticación:** Token en header `Authorization`
- **Token actual:** `5347d1ef4560d5f9e0bd8f77d114e0`

### 3.2 Endpoints usados

#### `GET /dialogs`
- Lista todos los diálogos
- Parámetros: `limit`, `offset`
- Cada diálogo tiene: `id`, `state`, `begin`, `end`, `last_request_id`, `operator_id`
- `last_request_id` vincula el diálogo con el último request

#### `GET /messages`
- Obtiene mensajes
- Parámetros que SÍ funcionan: `dialog_id`, `order`, `limit`, `offset`, `start_date`, `finish_date`
- Parámetros que NO funcionan: `request_id` (API lo ignora silenciosamente)
- `order=desc` es **fundamental** para obtener mensajes recientes primero

### 3.3 Estructura de un mensaje (JSON)
```json
{
  "id": 963360592,
  "dialog_id": 31170845,
  "request_id": 113591968,
  "client_id": 315533143,
  "operator_id": 31911,
  "text": "mensaje",
  "type": "from_client|to_client|autoreply|system",
  "created": "2026-07-10T17:57:56 UTC",
  "transport": "wa_dialog",
  "read": 1,
  "attachments": [],
  "photo": null,
  "video": null,
  "audio": null
}
```

Keys completas del objeto mensaje:
`id`, `text`, `pdf`, `coordinates`, `transport`, `type`, `read`, `created`, `remote_id`,
`recipient_status`, `ai_tips`, `dialog_id`, `operator_id`, `channel_id`, `is_new`,
`attachments`, `photo`, `video`, `audio`, `client_id`, `request_id`, `extra_data`, `status`

### 3.4 Conceptos clave
- **DialogID:** Identificador único del hilo de conversación con un cliente (nunca cambia)
- **RequestID:** Identificador de una sesión/solicitud dentro de un diálogo. Se crea uno nuevo
  cada vez que se abre y cierra un chat. En la interfaz web se ve como `N-113591968`
  (el número sin la `N-` es el RequestID)
- Los mensajes de un mismo RequestID comparten el mismo valor en el campo `request_id`
- El diálogo tiene `last_request_id` que indica el request más reciente

### 3.5 Comportamiento de `GET /messages`
- **Por defecto:** orden ascendente (más antiguos primero)
- **Con `order=desc`:** orden descendente (más recientes primero)
- La API puede limitar a ~200 mensajes por página (aunque se solicite un `limit` mayor)
- Para obtener más, usar paginación con `offset`

---

## 4. Plan futuro: Integración con MariaDB

### 4.1 Esquema de base de datos (tabla única)
```sql
CREATE TABLE IF NOT EXISTS mensajes_request (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    dialog_id INT NOT NULL,
    mensaje_id BIGINT,
    client_id INT,
    operator_id INT,
    tipo VARCHAR(20),
    texto TEXT,
    transport VARCHAR(30),
    fecha_creacion DATETIME,
    INDEX idx_request (request_id),
    INDEX idx_mensaje_id (mensaje_id)
);
```

### 4.2 Modificaciones planeadas al script
- Agregar dependencia: `mysql-connector-python` o `pymysql`
- Nuevo parámetro `--save-db` para guardar en BD además del CSV
- Variables de configuración de conexión en el archivo (host, user, password, database)
- Insertar mensajes en la tabla al detectar un RequestID

### 4.3 MariaDB no necesita virtualización
- Se instala como servicio directamente en el SO del servidor
- Windows: instalador MSI desde mariadb.org
- Linux: `sudo apt install mariadb-server`
- Docker: `docker run --name mariadb -e MYSQL_ROOT_PASSWORD=root -d mariadb`

---

## 5. Instalación en el servidor

### 5.1 Python
```powershell
python --version  # Debe ser 3.13+
```

### 5.2 Librerías
```powershell
pip install requests
# Futuro:
# pip install mysql-connector-python
```

### 5.3 Archivos a transferir
- `Extraer chat de dialogos.py`
- `Extraer_por_request.py`

---

## 6. Comandos de uso

```powershell
# Extraer por RequestID + DialogID (más rápido)
python Extraer_por_request.py --request-id 113591968 --dialog-id 31170845

# Extraer solo por RequestID (lento, busca en todos los diálogos)
python Extraer_por_request.py --request-id 113591968

# Extraer por RequestID + rango de fechas
python Extraer_por_request.py --request-id 113591968 --date-from "2026-07-10 11:55" --date-to "2026-07-10 17:58"

# Editar variables directo en el archivo y ejecutar sin argumentos
python Extraer_por_request.py

# Script original (descarga masiva)
python "Extraer chat de dialogos.py"
```

---

## 7. Notas importantes

- El filtro `?request_id=X` en `GET /messages` NO funciona (API lo ignora)
- Siempre usar `order=desc` al buscar mensajes recientes
- El RequestID se muestra en la interfaz web como `N-113591968` - usar solo el número sin el prefijo
- El archivo `request_{request_id}.csv` se genera en la misma carpeta del script
- Si el CSV ya existe y está abierto (ej: Excel), el script falla con `PermissionError` - cerrar el archivo primero
- Para que opencode del server entienda el contexto, solo basta con que lea este documento y los archivos .py
