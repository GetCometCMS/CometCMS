# API de Administración

La API de administración impulsa la interfaz del panel de administración construida en Vue (SPA). Utiliza **autenticación de sesión de PHP** (basada en cookies). Todos los endpoints requieren una sesión activa de administrador a menos que se indique lo contrario.

> Estos endpoints están destinados para el frontend del panel de administración. Para acceder al contenido de forma "headless", utiliza la [API Pública](./public-api) con un token de API en su lugar.

## URL Base

```
https://tudominio.com/admin/api
```

---

## Autenticación (Auth)

| Método | Ruta | Descripción |
| --- | --- | --- |
| `POST` | `/admin/api/setup` | Configuración de primer inicio — crea el admin inicial (solo funciona cuando no existen usuarios). |
| `GET` | `/admin/api/me` | Devuelve el usuario autenticado actualmente. |
| `POST` | `/admin/api/login` | Inicia sesión con `username` + `password`. |
| `POST` | `/admin/api/logout` | Cierra sesión y destruye la sesión. |

---

## Panel de Control (Dashboard)

| Método | Ruta | Descripción |
| --- | --- | --- |
| `GET` | `/admin/api/dashboard` | Estadísticas de resumen (conteos de entradas, actividad reciente). |
| `GET` | `/admin/api/app` | Versión de la aplicación e información de configuración. |
| `GET` | `/admin/api/activity` | Registro de actividad paginado. Soporta `level`, `type`, `limit`, y `offset`. |
| `GET` | `/admin/api/update` | Estado de actualización actual. |
| `POST` | `/admin/api/update/check` | Busca actualizaciones. Requiere `updates.check`. |
| `POST` | `/admin/api/update/download` | Descarga la actualización más reciente al entorno de prueba (staging). Requiere `updates.download`. |
| `POST` | `/admin/api/update/install` | Instala una actualización preparada. Requiere `updates.install`. |

---

## Espacios de Trabajo (Workspaces)

Las peticiones de administración deben enviar el encabezado `X-Comet-Workspace` para interactuar con datos específicos del espacio de trabajo (contenido, esquema de tipos de contenido, medios). Si se omite, se usa el espacio de trabajo predeterminado configurado.

| Método | Ruta | Descripción |
| --- | --- | --- |
| `GET` | `/admin/api/workspaces` | Listar todos los espacios de trabajo. |
| `POST` | `/admin/api/workspaces` | Crear un espacio de trabajo. |
| `GET` | `/admin/api/workspaces/{slug}` | Obtener detalles de un espacio de trabajo. |
| `PUT` | `/admin/api/workspaces/{slug}` | Actualizar un espacio de trabajo (ej. etiqueta). |
| `DELETE` | `/admin/api/workspaces/{slug}` | Eliminar de forma permanente un espacio de trabajo. |
| `POST` | `/admin/api/workspaces/{slug}/icon` | Subir el icono de un espacio de trabajo (`multipart/form-data`). |
| `DELETE` | `/admin/api/workspaces/{slug}/icon` | Eliminar el icono de un espacio de trabajo. |
| `POST` | `/admin/api/workspaces/{slug}/archive` | Archivar un espacio de trabajo (lo oculta). |
| `POST` | `/admin/api/workspaces/{slug}/unarchive` | Desarchivar un espacio de trabajo. |
| `POST` | `/admin/api/workspaces/{slug}/default` | Establecer el espacio de trabajo como el valor predeterminado del sistema. |

---

## Tipos de contenido

| Método | Ruta | Descripción |
| --- | --- | --- |
| `GET` | `/admin/api/content-types` | Listar todos los esquemas. |
| `POST` | `/admin/api/content-types` | Crear un nuevo esquema. |
| `GET` | `/admin/api/content-types/{nombre}` | Leer un esquema de tipo de contenido específico. |
| `PUT` | `/admin/api/content-types/{nombre}` | Actualizar un esquema (campos, configuraciones). |
| `DELETE` | `/admin/api/content-types/{nombre}` | Eliminar un tipo de contenido (y todas sus entradas). |

---

## Contenido

| Método | Ruta | Descripción |
| --- | --- | --- |
| `GET` | `/admin/api/content/{colección}` | Listar entradas en un tipo de contenido. Soporta `q`, `sort`, `limit`, `offset`, `filter`, e `include`. |
| `POST` | `/admin/api/content/{colección}` | Crear una nueva entrada. |
| `GET` | `/admin/api/content/{colección}/{id}` | Obtener una entrada para edición. |
| `PUT` | `/admin/api/content/{colección}/{id}` | Actualizar una entrada. |
| `DELETE` | `/admin/api/content/{colección}/{id}` | Eliminar una entrada (se mueve a la papelera). |
| `POST` | `/admin/api/content/{colección}/{id}/publish` | Marcar como publicado. |
| `POST` | `/admin/api/content/{colección}/{id}/unpublish` | Marcar como borrador. |
| `POST` | `/admin/api/content/{colección}/action` | Acciones en bloque (`delete`, `publish`, `unpublish`, `set_field`). Enviar `{ action, ids, payload }`. |

---

## Historial de Revisiones

| Método | Ruta | Descripción |
| --- | --- | --- |
| `GET` | `/admin/api/content/{colección}/{id}/revisions` | Listar todas las instantáneas de revisión para una entrada. |
| `GET` | `/admin/api/content/{colección}/{id}/revisions/{rev_id}` | Obtener el estado exacto de una revisión específica. |
| `POST` | `/admin/api/content/{colección}/{id}/revisions/{rev_id}/restore` | Restaurar el contenido de una revisión. |

---

## Papelera (Trash)

| Método | Ruta | Descripción |
| --- | --- | --- |
| `GET` | `/admin/api/trash` | Listar todas las entradas eliminadas temporalmente en el espacio de trabajo activo. |
| `POST` | `/admin/api/trash/{id}/restore` | Restaurar una entrada a su colección. |
| `DELETE` | `/admin/api/trash/{id}` | Eliminar permanentemente una entrada de la papelera. |
| `POST` | `/admin/api/trash/empty` | Eliminar de forma permanente todas las entradas de la papelera del espacio de trabajo activo. |

---

## Medios (Multimedia)

| Método | Ruta | Descripción |
| --- | --- | --- |
| `GET` | `/admin/api/media` | Listar medios. Soporta `limit`, `offset`, `q`, `sort`, `category`. |
| `POST` | `/admin/api/media` | Subir medios (Matriz de archivos en form-data). |
| `POST` | `/admin/api/media/url` | Añadir medio por URL. Envía `{"url": "https://..."}`. |
| `PUT` | `/admin/api/media/{archivo}` | Actualizar los metadatos (alt, título, etc.). |
| `DELETE` | `/admin/api/media/{archivo}` | Eliminar archivo y metadatos. |
| `POST` | `/admin/api/media/action` | Acción masiva (`delete`, `set_field`). Envía `{ action, ids, payload }`. |
| `GET` | `/admin/api/media-categories` | Listar todas las categorías de medios en el espacio de trabajo. |
| `POST` | `/admin/api/media-categories` | Crear una nueva categoría de medios. |
| `PUT` | `/admin/api/media-categories/{nombre}` | Renombrar o mover (establecer nuevo nombre padre/hijo) una categoría de medios. |
| `DELETE` | `/admin/api/media-categories/{nombre}` | Eliminar una categoría (los archivos de esa categoría se quedan sin categoría). |

---

## Usuarios, Roles, y Tokens

| Método | Ruta | Descripción |
| --- | --- | --- |
| `GET` | `/admin/api/users` | Listar todos los usuarios. |
| `POST` | `/admin/api/users` | Crear un nuevo usuario (requiere rol `admin`). |
| `GET` | `/admin/api/users/{id}` | Obtener detalles del usuario. |
| `PUT` | `/admin/api/users/{id}` | Actualizar un usuario. |
| `DELETE` | `/admin/api/users/{id}` | Eliminar un usuario. |
| `GET` | `/admin/api/roles` | Listar todos los roles configurados. |
| `POST` | `/admin/api/roles` | Crear un nuevo rol con permisos. |
| `GET` | `/admin/api/roles/{rol}` | Obtener permisos del rol. |
| `PUT` | `/admin/api/roles/{rol}` | Actualizar permisos del rol. |
| `DELETE` | `/admin/api/roles/{rol}` | Eliminar el rol. |
| `GET` | `/admin/api/tokens` | Listar todos los tokens de API de la aplicación. |
| `POST` | `/admin/api/tokens` | Generar un nuevo token con un array de permisos (grants). |
| `DELETE` | `/admin/api/tokens/{id}` | Revocar un token. |
| `PUT` | `/admin/api/profile` | Actualizar el propio perfil (nombre, correo). |
| `PUT` | `/admin/api/profile/password` | Cambiar la propia contraseña (requiere la actual). |
| `POST` | `/admin/api/profile/avatar` | Subir o reemplazar el propio avatar. |
| `DELETE` | `/admin/api/profile/avatar` | Eliminar el propio avatar. |

---

## Copias de Seguridad (Backups)

| Método | Ruta | Descripción |
| --- | --- | --- |
| `GET` | `/admin/api/backups` | Listar todos los archivos ZIP de copias de seguridad en `storage/backups/`. |
| `POST` | `/admin/api/backups` | Crear una nueva copia de seguridad (partes configurables a incluir). |
| `POST` | `/admin/api/backups/upload` | Subir un archivo de copia de seguridad (`multipart/form-data`). |
| `GET` | `/admin/api/backups/{nombre_archivo}` | Inspeccionar el contenido del ZIP (cuenta de partes). |
| `DELETE` | `/admin/api/backups/{nombre_archivo}` | Eliminar el archivo de copia de seguridad. |
| `POST` | `/admin/api/backups/{nombre_archivo}/restore` | Restaurar partes seleccionadas desde el ZIP. |

---

## Webhooks

| Método | Ruta | Descripción |
| --- | --- | --- |
| `GET` | `/admin/api/webhooks` | Listar todas las configuraciones de webhooks. |
| `POST` | `/admin/api/webhooks` | Crear un nuevo webhook. |
| `GET` | `/admin/api/webhooks/{id}` | Obtener un webhook por ID. |
| `PUT` | `/admin/api/webhooks/{id}` | Actualizar un webhook. |
| `DELETE` | `/admin/api/webhooks/{id}` | Eliminar un webhook. |