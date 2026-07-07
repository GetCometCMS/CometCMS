# Entradas de Contenido

## Navegar por las entradas

Selecciona una colección desde la **barra lateral** para ver una lista de todas las entradas en esa colección. La lista muestra el título/slug, estado, autor y la fecha de la última actualización.

Los tipos de contenido de página única (Single page) aparecen en la sección **Single (Individual)** de la barra lateral. Estos se abren directamente en el editor en lugar de mostrar una lista.

## Crear una entrada

1. Abre una colección desde la barra lateral.
2. Haz clic en **Nueva entrada (New entry)**.
3. Rellena los campos.
4. Haz clic en **Guardar (Save)**.

Las entradas se crean con el estado de `draft` (borrador) por defecto. Alterna el interruptor **Publicado (Published)** (o un campo `boolean` / `datetime` de tu elección) para publicarlas.

Si el tipo de contenido tiene idiomas configurados, las nuevas entradas inician en el idioma predeterminado a menos que elijas otro. El slug de la entrada, el estado, el autor y la fecha de publicación se comparten entre todos los idiomas.

## Editar una entrada

Haz clic en cualquier fila de la lista para abrir el editor de la entrada. Los cambios se guardan cuando haces clic en **Guardar entrada (Save entry)**.

Las entradas con múltiples idiomas muestran "píldoras" de idiomas sobre el formulario. Las píldoras sólidas tienen traducciones guardadas, las píldoras punteadas crean una variante de idioma que falta, y el idioma predeterminado está etiquetado. Eliminar una variante de idioma solo elimina esa traducción; la entrada y los demás idiomas permanecen intactos.

## Historial de la entrada

Cada vez que guardas una entrada, se almacena una instantánea (snapshot) de revisión. CometCMS mantiene hasta el límite de `content.max_revisions` instantáneas por entrada, que por defecto es `50` en `config/config.php`. Puedes configurarlo a `0` para deshabilitar el historial de revisiones, o a un valor negativo para guardar revisiones indefinidamente.

Abre el panel lateral de **Historial de entrada (Entry history)** para:

- Navegar por todas las versiones anteriores, incluyendo qué usuario guardó cada una.
- Ver un **diff (diferencias)** de lo que cambió entre cada revisión.
- **Restaurar** cualquier revisión haciendo clic en el icono de restaurar — esto carga los valores antiguos en el editor sin guardar. Revisa los cambios y haz clic en **Guardar entrada (Save entry)** para aplicarlos de forma definitiva.

La parte superior de la lista del historial siempre muestra el estado **actual (current)** con una etiqueta azul.

## Eliminar una entrada

Haz clic en **Eliminar (Delete)** en una entrada para moverla a la **Papelera (Trash)**. Las entradas en la papelera pueden ser restauradas o eliminadas permanentemente desde la sección de Papelera.

Eliminar una página única (Single page) también mueve su única entrada a la papelera. Mientras esté en la papelera, la ruta directa del editor/API devuelve un error de "no encontrado" (not found) para las lecturas públicas. Restaurar la página eliminada la trae de vuelta en el mismo slug fijo, a menos que ya exista otra entrada activa para ese tipo de contenido de página única.

## Metadatos de la entrada

Cada entrada obtiene automáticamente estos campos del sistema (no son editables):

| Campo | Descripción |
| --- | --- |
| `id` | Identificador único (autogenerado). |
| `collection` | La colección a la que pertenece esta entrada. |
| `created_at` | Marca de tiempo (timestamp) ISO 8601 de cuando la entrada fue creada por primera vez. |
| `updated_at` | Marca de tiempo ISO 8601 del último guardado. |
| `author_id` | ID del usuario que creó la entrada. |
| `updated_by` | ID del usuario que actualizó la entrada por última vez. |