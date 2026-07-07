# API MCP

El endpoint de Model Context Protocol (MCP) permite que asistentes de codificación de IA (GitHub Copilot, Claude Code, Cursor, etc.) interactúen con CometCMS directamente desde el editor. Expone tipos de contenido, entradas y medios como **herramientas (tools)** que la IA puede descubrir y llamar a demanda.

> MCP es un protocolo servidor-a-servidor diseñado para que la IA realice llamadas a herramientas (tool calling). Para el consumo REST tradicional desde frontends o scripts, utiliza la [API Pública](./public-api) o la [API de Administración](./admin-api) en su lugar.

---

## URL Base

```
https://tudominio.com/mcp/v1/workspaces/{workspace}
```

El slug del espacio de trabajo siempre es parte de la URL — no existe un endpoint MCP sin alcance (unscoped).

---

## Protocolo

CometCMS implementa la [Especificación MCP](https://spec.modelcontextprotocol.io) (protocolo versión `2025-06-18`) sobre **JSON-RPC 2.0** utilizando **JSON sobre HTTP POST**.

| Aspecto | Detalle |
| --- | --- |
| Transporte | HTTP POST, `Content-Type: application/json` |
| Protocolo | JSON-RPC 2.0 + primitivas de herramientas MCP |
| Autenticación | Token Bearer (ver [Tokens de API](../guide/api-tokens)) |
| Encabezado requerido | `Authorization: Bearer TU_TOKEN_AQUI` |
| Peticiones no-POST | Devuelven `405 Method Not Allowed` |

### Ciclo de vida

1. **Inicializar** — el cliente llama a `initialize` para negociar la versión del protocolo y descubrir las capacidades del servidor.
2. **Descubrimiento de herramientas** — el cliente llama a `tools/list` para recuperar los esquemas de las herramientas disponibles.
3. **Llamadas a herramientas** — el cliente invoca herramientas individuales mediante `tools/call`.
4. **Notificaciones** — `notifications/initialized` y `ping` son aceptadas pero no producen respuesta.

Las notificaciones y solicitudes sin un campo `id` se tratan como operaciones "fire-and-forget" y devuelven `202 Accepted`.

---

## Permisos (Grants)

La API MCP utiliza los mismos Tokens de API y sistema de permisos (grants) que la API REST. Un asistente de IA que use un token con acceso restringido (por ejemplo, con permisos solo de lectura) generará errores apropiados si intenta llamar a una herramienta de escritura.

La herramienta `list_content_types` devuelve _todos_ los tipos de contenido independientemente de los permisos de nivel de contenido del token; el cumplimiento de permisos ocurre en tiempo de lectura/escritura (read/write time) mediante las herramientas específicas del contenido.

---

## Herramientas soportadas (Tools)

CometCMS expone las siguientes funciones al cliente MCP a través de `tools/list`:

### Esquema y Tipos de Contenido

| Nombre de la herramienta | Descripción | Parámetros MCP (Entrada JSON) |
| --- | --- | --- |
| `list_content_types` | Devuelve una lista de los identificadores de todos los tipos de contenido definidos (colecciones y páginas únicas) y sus etiquetas. | Ninguno |
| `get_content_type` | Obtiene el esquema completo JSON para un tipo de contenido, detallando qué campos soporta. | `type` (obligatorio) |

### Gestión de Contenido

| Nombre de la herramienta | Descripción | Parámetros MCP (Entrada JSON) |
| --- | --- | --- |
| `list_entries` | Obtiene entradas de un tipo de contenido con opciones de paginación y búsqueda. | `type` (obligatorio), `limit`, `offset`, `search`, `locale`, `filter` |
| `get_entry` | Obtiene los detalles completos de una entrada específica. | `type` (obligatorio), `id_or_slug` (obligatorio), `locale` |
| `create_entry` | Crea una nueva entrada. Los campos pasados deben coincidir con el esquema. | `type` (obligatorio), `locale`, `{fields...}` |
| `update_entry` | Actualiza una entrada de forma parcial. | `type` (obligatorio), `id_or_slug` (obligatorio), `locale`, `{fields...}` |
| `delete_entry` | Envía una entrada a la papelera. | `type` (obligatorio), `id_or_slug` (obligatorio) |

### Gestión de Medios

| Nombre de la herramienta | Descripción | Parámetros MCP (Entrada JSON) |
| --- | --- | --- |
| `list_media` | Obtiene los archivos multimedia subidos (limitado a metadatos, no al binario del archivo). | `limit`, `offset`, `search`, `category` |
| `get_media` | Obtiene los metadatos de un archivo multimedia específico. | `filename` (obligatorio) |
| `update_media` | Actualiza los metadatos de los medios (alt, título, categoría, visibilidad). | `filename` (obligatorio), `{fields...}` |
| `delete_media` | Elimina un archivo multimedia de forma permanente. | `filename` (obligatorio) |

> La subida binaria de archivos no está soportada a través de MCP debido a que MCP se transporta de forma nativa como JSON. Usa el endpoint `/api/v1/workspaces/{workspace}/media` con `multipart/form-data` para las subidas de archivos en su lugar.

---

## Respuestas JSON-RPC

Una llamada `tools/call` exitosa devuelve un `result` estructurado con la matriz `content` en el formato requerido por MCP. Los tipos de contenido soportados dentro de los resultados MCP son `text` (para texto plano y JSON stringificado).

Si ocurre un error durante el procesamiento, CometCMS devuelve una carga útil estructurada (payload) `error` de JSON-RPC, no un código de estado de error HTTP. La respuesta HTTP en sí permanece como `200 OK` (como lo define MCP para errores de protocolo).

**Ejemplo de respuesta de error:**

```json
{
  "jsonrpc": "2.0",
  "id": "1",
  "error": {
    "code": -32602,
    "message": "Invalid params",
    "data": {
      "details": "The 'type' parameter is required."
    }
  }
}
```

Códigos de error JSON-RPC comunes:

- `-32700` Parse error (Error de análisis - cuerpo JSON malformado)
- `-32600` Invalid Request (Petición inválida - falta `method` o carga no-MCP)
- `-32601` Method not found (Método no encontrado - se solicitó algo distinto a las cuatro acciones principales)
- `-32602` Invalid params (Parámetros inválidos - la herramienta fue llamada con argumentos faltantes o incorrectos)
- `-32000` Server error (Error del servidor - fallo genérico de la aplicación, como `not_found` o `permission_denied`)