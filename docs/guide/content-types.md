# Tipos de Contenido

Un **tipo de contenido** define la estructura (esquema) para el contenido. La mayoría de los tipos de contenido son colecciones de entradas, como `posts`. Un tipo de contenido también puede ser marcado como **página única** (single page) para contenido único como `start-page` (página de inicio), `contact-page` (página de contacto) o `imprint` (aviso legal).

![Resumen de los tipos de contenido en la administración de CometCMS](../screenshots/view-content-types.png)

## Crear un tipo de contenido

1. En la barra lateral, haz clic en **Tipos de contenido (Content types)**.
2. Haz clic en **Nuevo tipo de contenido (New content type)**.
3. Introduce un **nombre** (ej. `posts`). El nombre se usa como el identificador de la colección en la API — utiliza solo letras minúsculas, números y guiones.
4. Elige el **Modelo de contenido (Content model)**:
   - **Colección (Collection)** para contenido repetible con muchas entradas.
   - **Página única (Single page)** para una sola entrada fija.
5. Añade campos (ver [Tipos de Campos](./field-types)).
6. Opcional: añade configuraciones de idiomas (locales) y elige un idioma predeterminado.
7. Haz clic en **Guardar (Save)**.

Los tipos de contenido de página única aparecen bajo **Single (Individual)** en la barra lateral y se abren directamente en el editor. El slug de su entrada está fijo y coincide con el nombre del tipo de contenido, y la URL de lectura predeterminada de la API es `/api/v1/workspaces/{workspace}/content/start-page`.

## Editar un tipo de contenido

Abre un tipo de contenido existente para añadir, reordenar, eliminar campos, o configurar valores predeterminados de los campos compatibles. Los cambios en el esquema no afectan a las entradas existentes — las entradas antiguas simplemente no tendrán el nuevo valor del campo hasta que sean editadas y guardadas.

![Editando el esquema de un tipo de contenido en CometCMS](../screenshots/edit-content-type.png)

Los valores predeterminados de los campos se rellenan automáticamente en las entradas nuevas en el editor de administración y se aplican a las entradas creadas mediante la API cuando se omite el campo.

Puedes cambiar una colección a una página única solo cuando tiene como máximo una entrada activa. Esto evita ambigüedades sobre qué entrada existente debería convertirse en el contenido de la página fija.

## Localización (Idiomas)

Los tipos de contenido pueden definir múltiples `locales` y un `default_locale`. Deja la configuración de locales vacía para desactivar la edición multilingüe para ese tipo.

Las entradas localizadas guardan el campo `title` (título) y los valores de los campos personalizados traducidos por idioma. El slug, estado, autor, fecha de publicación, las marcas de tiempo (timestamps) y el ID de la entrada son compartidos por todos los idiomas.

Cambiar las configuraciones de los idiomas es una acción no destructiva:

- Añadir un idioma lo hace disponible para futuras traducciones. Las entradas existentes lo mostrarán como faltante hasta que un editor guarde contenido en ese idioma.
- Habilitar la localización para un tipo de contenido no localizado previamente copia el contenido raíz de cada entrada hacia el nuevo idioma predeterminado.
- Eliminar un idioma lo oculta del editor y de la resolución con `?locale=`, pero los datos de la traducción guardados se conservan en el almacenamiento.
- Cambiar el idioma predeterminado actualiza las entradas existentes para usar dicho idioma como el valor raíz de respaldo (fallback) cuando existe una traducción.
- Desactivar la localización mantiene los datos de traducción en el almacenamiento, pero tanto la administración como la API utilizarán los valores raíz compartidos hasta que la localización vuelva a habilitarse.

## Acceso API restringido por espacio de trabajo (Workspaces)

Los esquemas de tipos de contenido están restringidos a su espacio de trabajo. El mismo nombre de tipo de contenido puede existir con diferentes definiciones de campos en diferentes espacios de trabajo.

Para la API Pública, utiliza el prefijo del espacio de trabajo:

```http
GET /api/v1/workspaces/site-b/content-types
GET /api/v1/workspaces/site-b/content-types/posts
```

Las rutas de la API Pública sin el prefijo del espacio de trabajo bajo `/api/v1/...` son rechazadas con el error `workspace_required`.

Para solicitudes a la API de Administración, el espacio de trabajo activo se selecciona con el encabezado `X-Comet-Workspace` (o el espacio de trabajo predeterminado configurado si el encabezado es omitido):

```http
X-Comet-Workspace: site-b
```

## Eliminar un tipo de contenido

Eliminar un tipo de contenido también borra todas las entradas en esa colección. Esta acción es irreversible.

## Esquema de tipo de contenido (JSON)

Los tipos de contenido se almacenan en `cms/storage/content-types/{nombre}.json`. Un esquema típico se ve así:

```json
{
  "name": "posts",
  "label": "Posts",
  "singleton": false,
  "locales": ["en", "es"],
  "default_locale": "en",
  "fields": {
    "title": { "type": "text", "required": true },
    "slug": { "type": "slug", "required": true, "unique": true },
    "body": {
      "type": "markdown",
      "label": "Cuerpo",
      "default": "Empieza a escribir..."
    },
    "published": { "type": "boolean", "label": "Publicado", "default": false },
    "published_at": { "type": "datetime", "label": "Fecha de publicación" }
  }
}
```