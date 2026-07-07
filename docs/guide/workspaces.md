# Espacios de Trabajo (Workspaces)

Los espacios de trabajo (workspaces) te permiten particionar el contenido, los medios y los tipos de contenido dentro de una misma instalación de CometCMS. Cada espacio de trabajo está completamente aislado — sus entradas, esquemas y archivos subidos se almacenan por separado.

Casos de uso comunes:

- Gestionar múltiples sitios web o aplicaciones desde un solo panel de administración.
- Separar contenido de pruebas (staging) y producción sin necesidad de ejecutar dos instancias del CMS.
- Dar a diferentes equipos su propia área de contenido aislada con permisos específicos (scoped).

## Qué se aísla y qué se comparte

Datos aislados por espacio de trabajo:

- Entradas de contenido
- Esquemas de tipos de contenido
- Archivos multimedia y metadatos de los medios
- Historial de revisiones
- Papelera (Trash)
- Caché de la API pública

Datos compartidos en toda la instalación:

- Usuarios
- Roles
- Tokens de API
- Configuraciones de Webhooks
- Archivos de copias de seguridad (Backups)
- Configuraciones de actualizaciones y opciones de tiempo de ejecución de la aplicación

Esto significa que puedes mantener un único directorio de equipo y modelo de autenticación mientras separas los datos de los sitios web por espacio de trabajo.

## Cómo se mapean los espacios de trabajo en las rutas de la API

Cada espacio de trabajo tiene un **slug** (un identificador corto seguro para URLs). El slug determina qué prefijo de ruta de la API se utiliza para acceder al contenido de ese espacio de trabajo.

### Rutas de la API Pública

Las rutas de la API pública siempre están restringidas al espacio de trabajo a través del prefijo de la URL:

```http
GET /api/v1/workspaces/site-a/content/posts
GET /api/v1/workspaces/site-a/content-types/posts
GET /api/v1/workspaces/site-a/media
```

Las rutas sin este alcance bajo `/api/v1/...` devolverán un error `workspace_required`.

### Selección de espacio de trabajo en la API de Administración

Las rutas de la API de administración seleccionan un espacio de trabajo a través del encabezado `X-Comet-Workspace`. Si se omite, CometCMS utiliza el espacio de trabajo predeterminado configurado.

```http
X-Comet-Workspace: site-b
```

| Patrón de ruta | Descripción |
| --- | --- |
| `GET /api/v1/workspaces/{slug}/content/{type}` | Listar entradas de un espacio de trabajo específico |
| `GET /api/v1/workspaces/{slug}/content-types/{type}` | Esquema del tipo de contenido de un espacio de trabajo específico |
| `GET /api/v1/workspaces/{slug}/media` | Lista de medios para un espacio de trabajo específico |
| `GET /media/{slug}/{filename}` | Servir archivo de medios desde un espacio de trabajo específico |
| `GET /media-thumbs/{slug}/{filename}` | Servir una miniatura generada de ese espacio de trabajo |

### Encabezado del espacio de trabajo (Workspace header)

Para los endpoints de administración (`/admin/api/...`), los clientes pueden seleccionar un espacio de trabajo por solicitud con `X-Comet-Workspace`:

```http
X-Comet-Workspace: site-b
```

Para endpoints públicos, usa `/api/v1/workspaces/{slug}/...`.

## Espacio de trabajo predeterminado

El espacio de trabajo integrado (por defecto) siempre se nombra `default` y no puede ser eliminado. Puedes cambiarle el nombre y subir un icono personalizado.

Para cambiar qué espacio de trabajo se sirve en la ruta principal de la API:

1. Ve a **Configuración → Workspaces**.
2. Haz clic en **Establecer como predeterminado (Set as default)** junto al espacio de trabajo que quieras promover.

El espacio de trabajo que era predeterminado anteriormente no se reasigna automáticamente — sigue accesible a través de su prefijo `/workspaces/{slug}`.

> **Nota:** El espacio de trabajo integrado `default` y el espacio de trabajo predeterminado _configurado_ son dos cosas distintas. El valor configurado por defecto puede ser cualquier espacio de trabajo; solo significa que su contenido se servirá en la ruta principal de la API.

## Creando un espacio de trabajo

1. Ve a **Configuración → Workspaces**.
2. Haz clic en **Nuevo espacio de trabajo (New workspace)**.
3. Introduce una etiqueta y (opcionalmente) un slug personalizado. El slug se deriva automáticamente de la etiqueta.
4. Haz clic en **Crear**.

Los slugs son inmutables después de la creación. Elígelos cuidadosamente.

## Archivar un espacio de trabajo

Los espacios de trabajo archivados se ocultan del selector de espacios de trabajo y de la API. Sus datos se conservan en el disco.

- El espacio de trabajo **integrado por defecto** (slug `default`) no puede ser archivado.
- El espacio de trabajo predeterminado **configurado** no puede archivarse hasta que asignes uno nuevo por defecto.

Para archivar: haz clic en **Archivar** junto al espacio de trabajo.

## Eliminar un espacio de trabajo

Eliminar un espacio de trabajo borra su registro permanentemente. Los archivos de contenido subyacentes permanecen en `cms/storage/` pero ya no son accesibles a través de la API.

- El espacio de trabajo integrado `default` no puede ser eliminado.
- El espacio de trabajo predeterminado configurado no puede ser eliminado hasta que establezcas uno diferente por defecto.

Para eliminar: haz clic en **Eliminar** junto al espacio de trabajo, luego escribe el slug del espacio de trabajo para confirmar.

## Iconos de los espacios de trabajo

Sube un icono (JPEG, PNG, WebP, o GIF — máx 10 MB) para ayudar a distinguir visualmente los espacios de trabajo en el selector y en la lista de administración.

Haz clic en el avatar del espacio de trabajo para subir un nuevo icono. Para eliminar uno existente, haz clic en **Eliminar icono** en la fila de acciones.

## Permisos y alcance de los espacios de trabajo

Los permisos pueden restringirse a un espacio de trabajo específico. Por ejemplo:

| Permiso (Grant) | Efecto |
| --- | --- |
| `workspace:*:content:*` | Lectura/escritura de contenido en todos los espacios de trabajo |
| `workspace:site-b:content:*` | Lectura/escritura de contenido solo en el espacio de trabajo `site-b` |
| `workspace:site-b:content:posts:read` | Acceso solo lectura a la colección `posts` en `site-b` |

Cuando crees o edites un token de API o rol de usuario, elige un espacio de trabajo del menú desplegable **Alcance (Scope)** en el editor de permisos para restringir un permiso a un solo espacio de trabajo.

## Diseño de almacenamiento

Cada espacio de trabajo almacena sus datos en subdirectorios restringidos a su alcance:

```
cms/storage/
  content/{workspace}/          # Entradas de contenido
  content-types/{workspace}/    # Esquemas de tipos de contenido
  media/{workspace}/            # Archivos multimedia subidos
  media-meta/{workspace}/       # Metadatos de medios
  media-thumbs/{workspace}/     # Miniaturas generadas
  revisions/{workspace}/        # Historial de revisiones
  trash/{workspace}/            # Entradas eliminadas (papelera)
  workspaces/icons/             # Iconos de los espacios de trabajo
```

El espacio de trabajo predeterminado utiliza `default` como el nombre de su directorio sin importar cuál sea el slug configurado por defecto.