# Tipos de Campos

Cada campo en un tipo de contenido tiene un `type` (tipo) que controla cómo se edita en el panel de administración y cómo se guarda su valor.

Los campos compatibles pueden incluir la opción `default` (predeterminado). Los valores predeterminados rellenan automáticamente las nuevas entradas en el editor de administración y también se aplican cuando las entradas se crean a través de la API omitiendo dicho campo. Los valores predeterminados están soportados en los campos `text`, `textarea`, `markdown`, `html`, `number`, `range`, `boolean`, `select`, `date`, `datetime`, `json` y `color`.

## Campos de texto

### `text`

Una entrada de texto de una sola línea.

| Opción | Descripción |
| --- | --- |
| `required` | Determina si el campo debe tener un valor antes de guardarse. |
| `default` | Valor inicial utilizado para nuevas entradas cuando se omite el campo. |

---

### `textarea`

Un área de texto sin formato de varias líneas.

Soporta `default`.

---

### `markdown`

Un editor enriquecido de Markdown con panel de vista previa en tiempo real. El valor se guarda como una cadena de texto en Markdown.

Soporta `default`.

---

### `html`

Un editor enriquecido de HTML con modos visual y código HTML. El valor se guarda como una cadena HTML saneada. Las etiquetas no compatibles y los atributos inseguros como scripts, manejadores de eventos en línea (inline events), estilos en línea y URLs `javascript:` se eliminan cuando se guarda el contenido.

Soporta `default`.

---

### `slug`

Un identificador seguro para URLs (minúsculas, guiones). Opcionalmente puede ser autogenerado a partir de otro campo.

| Opción | Descripción |
| --- | --- |
| `source` | La clave (`key`) de otro campo desde donde generar el slug (ej. `"title"`). |

---

## Campos numéricos

### `number`

Una entrada numérica. Guarda el valor como un número.

Soporta `default`.

---

### `range`

Una entrada de control deslizante (slider) con mínimo/máximo/incremento configurable y precisión visual de decimales.

| Opción | Descripción |
| --- | --- |
| `min` | Valor mínimo (por defecto `0`). |
| `max` | Valor máximo (por defecto `100`). |
| `step` | Incremento de paso para el deslizador (por defecto `1`). |
| `default` | Valor inicial del deslizador para nuevas entradas. |
| `display_decimals` | Precisión mostrada: `0`, `1`, `2`, `3` o `full` (por defecto `0`). |

---

## Booleanos

### `boolean`

Un interruptor (verdadero/falso). Guardado como un booleano en JSON.

Soporta `default`.

---

## Fecha y hora

### `date`

Un selector de fecha. Guarda una cadena de fecha en formato ISO 8601 (`YYYY-MM-DD`).

Soporta `default`.

---

### `datetime`

Un selector de fecha + hora. Guarda una cadena completa de fecha y hora en formato ISO 8601.

Soporta `default`.

---

## Selección

### `select`

Un menú desplegable con opciones predefinidas.

| Opción | Descripción |
| --- | --- |
| `options` | Arreglo de objetos `{ value, label }` o cadenas de texto simples. |
| `multiple` | Permite seleccionar más de un valor. Los valores multiselección se guardan como arreglos (arrays). |
| `default` | Valor de opción inicial, o un arreglo de valores para la multiselección. |

Ejemplo de definición de campo:

```json
{
  "key": "status",
  "type": "select",
  "label": "Estado",
  "options": ["borrador", "publicado", "archivado"]
}
```

---

## Medios (Multimedia)

### `media`

Un selector de medios que escoge uno o más archivos de la [Biblioteca de Medios](./media).

| Opción | Descripción |
| --- | --- |
| `multiple` | Permite seleccionar más de un archivo en el panel de administración. |

Los valores de los medios se guardan como un arreglo de nombres de archivo. Para campos de medios de selección única, la UI de administración limita la selección a un elemento. Las respuestas de la API Pública devuelven los campos de medios como arreglos de URLs absolutas de medios.

---

## Relacionales

### `relation`

Enlaza una entrada a una o más entradas en otra (o en la misma) colección.

| Opción | Descripción |
| --- | --- |
| `target` | El nombre del tipo de contenido de destino (ej. `"autores"`). |
| `multiple` | Permite seleccionar múltiples entradas relacionadas. |

Guarda el `id` de la entrada referenciada. Los campos multi-relacionales guardan un arreglo de ids.

---

## Estructurados

### `json`

Un editor de JSON crudo. Útil para guardar datos estructurados arbitrarios. El valor se guarda tal cual en el JSON de la entrada.

Soporta `default`.

---

## Color

### `color`

Un selector de color. Guarda una cadena de color en formato hex, como `#ff0000`.

Soporta `default`.