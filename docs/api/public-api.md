# API Pública

La API pública de CometCMS es la API HTTP estable para frontends externos, generadores de sitios estáticos, aplicaciones móviles y scripts de integración.

Las lecturas públicas funcionan sin autenticación y devuelven solo contenido público. Envía un token de API cuando necesites borradores, contenido protegido o acceso de escritura:

```http
Authorization: Bearer TU_TOKEN_AQUI
```

Consulta [Tokens de API](../guide/api-tokens) para saber cómo crear tokens y asignar permisos.

## URL Base

```text
https://tudominio.com/api/v1
```

Todos los endpoints de la API Pública requieren un segmento de espacio de trabajo (workspace) en la URL:

```text
https://tudominio.com/api/v1/workspaces/{workspace}
```

Las peticiones a rutas de contenido, tipos de contenido y medios bajo `/api/v1/...` que no tengan este alcance (unscoped) serán rechazadas con el error `workspace_required`.

Por ejemplo, `GET /api/v1/workspaces/site-a/content/posts` lee los posts del espacio de trabajo `site-a`. Las URLs directas de los medios para las respuestas limitadas por espacio de trabajo usan `/media/{workspace}/{filename}` y `/media-thumbs/{workspace}/{filename}`.

## Forma de la respuesta

Las respuestas JSON exitosas siempre están envueltas en `data`. Las respuestas de listas y los metadatos de respuesta secundaria utilizan `meta`.

```json
{
  "data": [],
  "meta": {
    "total": 0,
    "limit": 20,
    "offset": 0,
    "sort": "created_at",
    "locale": "en"
  }
}
```

## Manejo de errores

Los errores (códigos de estado 4xx y 5xx) envuelven los detalles en un objeto `error` estructurado:

```json
{
  "error": {
    "code": "not_found",
    "message": "The requested resource was not found."
  }
}
```

Algunos errores comunes incluyen `invalid_credentials`, `permission_denied`, `validation_failed`, y `invalid_request`.

---

## Filtros, Ordenación y Paginación

### Paginación

Para los endpoints de lista de colecciones y lista de medios:

- `limit` (número) — el número de resultados a devolver. Por defecto es `20`.
- `offset` (número) — los resultados a saltar antes de devolver. Por defecto es `0`.

```http
GET /api/v1/workspaces/{workspace}/content/posts?limit=10&offset=20
```

### Ordenación

Usa `sort={campo}` para ordenar los resultados de forma ascendente, o prefija con un `-` para orden descendente.

```http
GET /api/v1/workspaces/{workspace}/content/posts?sort=-published_at
```

### Búsqueda (Texto completo)

Usa `q={consulta}` para realizar una búsqueda insensible a mayúsculas y minúsculas en todos los campos de texto, HTML y Markdown del tipo de contenido (o nombres de archivos y metadatos en las respuestas de los medios).

```http
GET /api/v1/workspaces/{workspace}/content/posts?q=lanzamiento
```

### Filtros por campo

Restringe los resultados por valores específicos de los campos usando la sintaxis `filter[...]`.

#### Coincidencia exacta

```http
GET /api/v1/workspaces/{workspace}/content/posts?filter[status]=published
GET /api/v1/workspaces/{workspace}/content/posts?filter[is_featured]=true
```

#### Operadores

Añade un operador en corchetes anidados para condiciones avanzadas:

- `[in]` — El valor debe ser uno de los de una lista separada por comas.
- `[ne]` — No igual.
- `[gt]` — Mayor que.
- `[gte]` — Mayor o igual que.
- `[lt]` — Menor que.
- `[lte]` — Menor o igual que.
- `[contains]` — Búsqueda de subcadena insensible a mayúsculas/minúsculas.

```http
GET /api/v1/workspaces/{workspace}/content/posts?filter[category][in]=tech,news
GET /api/v1/workspaces/{workspace}/content/posts?filter[status][ne]=draft
GET /api/v1/workspaces/{workspace}/content/products?filter[price][lte]=100
GET /api/v1/workspaces/{workspace}/content/posts?filter[published_at][gte]=2024-01-01
GET /api/v1/workspaces/{workspace}/content/posts?filter[title][contains]=anuncio
```

Los filtros booleanos aceptan `true`/`false` o `1`/`0`. La comparación es sensible al tipo, de modo que `price[gt]=10` compara el número diez, y la comparación de fechas ISO 8601 se maneja correctamente.

Si un campo de tipo `select` permite la selección múltiple (un arreglo de valores en el JSON de la entrada), un filtro `filter[field]=val` o `filter[field][in]=val,val2` coincidirá si _al menos uno_ de los valores seleccionados en la entrada coincide.

## Localización e Inclusión de Relaciones

### Idiomas (Locales)

Añade `?locale={código}` para resolver el título y los campos localizados antes de aplicar filtros, búsqueda y ordenación. Si se omite, se utiliza el idioma por defecto del tipo de contenido. Si un campo no está traducido en el idioma solicitado, el sistema retrocede (fallbacks) al valor del idioma predeterminado de esa entrada.

```http
GET /api/v1/workspaces/{workspace}/content/posts?locale=es
```

### Inclusiones de relaciones (Includes)

Los campos definidos como `relation` o `media` devuelven el ID del recurso (o un arreglo de IDs) por defecto. Usa `?include=campo1,campo2` para incrustar el recurso completo relacionado directamente en la respuesta.

```http
GET /api/v1/workspaces/{workspace}/content/posts/mi-post?include=author,cover_image
```

Para las consultas de listas, las relaciones se extraen de forma eficiente en bloque y se integran en cada elemento de la respuesta en memoria.

---

## Tipos de contenido

### Listar esquemas de tipos de contenido

```http
GET /api/v1/workspaces/{workspace}/content-types
```

Devuelve una lista de las definiciones del esquema de los tipos de contenido en el espacio de trabajo.

### Leer un esquema de tipo de contenido

```http
GET /api/v1/workspaces/{workspace}/content-types/{nombre}
```

Devuelve el esquema de un tipo de contenido específico.

---

## Contenido

### Listar contenido (Colección)

```http
GET /api/v1/workspaces/{workspace}/content/{colección}
```

Devuelve una lista paginada de entradas para un tipo de contenido específico.

- Si no se proporciona un token de API, solo se devuelven las entradas con estado `published`.
- Si se proporciona un token, se devuelven todas las entradas, permitiéndote filtrar por `status` como lo requieras (sujeto a las restricciones de recursos del token).
- Se admiten los parámetros de consulta `limit`, `offset`, `sort`, `q`, `locale`, `filter[...]` e `include`.

### Leer una entrada (Colección o Página Única)

```http
GET /api/v1/workspaces/{workspace}/content/{colección}/{identificador}
```

Devuelve una única entrada. `{identificador}` puede ser el `id` o el `slug` de la entrada.

Si la entrada no está `published`, solo será devuelta si se usa un token de API válido.

Para los tipos de contenido de página única (Single pages), se recomienda omitir el `{identificador}` si se busca por la ruta base de la colección:

```http
GET /api/v1/workspaces/{workspace}/content/start-page
```

Cuando se pide la ruta base de una página única (sin `{identificador}`), CometCMS usa automáticamente el slug que coincide con el nombre de la colección.

### Crear una entrada

```http
POST /api/v1/workspaces/{workspace}/content/{colección}
```

Crea una nueva entrada. El cuerpo de la solicitud (request body) debe ser JSON conteniendo los valores de los campos. Los parámetros de URL `?locale={código}` determinan en qué idioma se guardarán los valores (si el tipo de contenido es localizable). Se requiere autenticación.

### Actualizar una entrada

```http
PUT /api/v1/workspaces/{workspace}/content/{colección}/{identificador}
```

Actualiza una entrada existente de forma parcial. El cuerpo (body) solo necesita incluir los campos que quieres cambiar. Usa `?locale={código}` para apuntar a un idioma específico. Se requiere autenticación.

### Eliminar una entrada

```http
DELETE /api/v1/workspaces/{workspace}/content/{colección}/{identificador}
```

Elimina temporalmente (mueve a la papelera) una entrada. Se requiere autenticación.

---

## Medios (Multimedia)

### Listar medios

```http
GET /api/v1/workspaces/{workspace}/media
```

Devuelve una lista paginada de archivos subidos en el espacio de trabajo actual. Soporta `limit`, `offset`, `q`, `sort` y filtrado exacto en `category`.

Solo se devuelven los archivos con una `visibility` de `public` a menos que se use un token de API válido.

### Cargar medios

```http
POST /api/v1/workspaces/{workspace}/media
Content-Type: multipart/form-data
```

Sube uno o más archivos. Envía un formulario `multipart/form-data` con una matriz de archivos bajo la clave `files[]`. Opcionalmente incluye `category` en la solicitud. Se requiere autenticación.

### Actualizar detalles de medios

```http
PUT /api/v1/workspaces/{workspace}/media/{nombre_del_archivo}
```

Actualiza los metadatos de un archivo (ej. `alt`, `title`, `category`, `visibility`). Se requiere autenticación.

### Eliminar medios

```http
DELETE /api/v1/workspaces/{workspace}/media/{nombre_del_archivo}
```

Elimina un archivo y sus metadatos de forma permanente. Se requiere autenticación.

---

## Caché y Webhooks

Las peticiones GET de la API pública a rutas de contenido se almacenan en caché automáticamente por la API. La respuesta en caché se limpia siempre que se crea, actualiza, elimina o publica contenido. El TTL del caché está configurado a través de `cache.ttl` en `config.php`.

Las aplicaciones externas (como proveedores de alojamiento estático) no deberían usar el TTL del caché como estrategia de invalidación, sino suscribirse a [Webhooks](../guide/webhooks) para desencadenar el re-despliegue en el momento que ocurra un evento de publicación o contenido.