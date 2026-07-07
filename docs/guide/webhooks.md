# Webhooks

Los webhooks permiten que CometCMS notifique a una URL externa siempre que el contenido cambie. El caso de uso principal es disparar trabajos de reconstrucción en generadores de sitios estáticos (SSG) — pero cualquier endpoint HTTP puede recibir estos eventos.

![Pantalla de webhooks en la administración de CometCMS](../screenshots/view-webhooks.png)

## Configuración

Los webhooks se configuran en la página de **Webhooks** de la administración (en **Sistema (System) → Webhooks**). Cada webhook tiene:

| Campo | Descripción |
| --- | --- |
| **URL** | El endpoint HTTPS que recibirá las peticiones POST. |
| **Secret (Secreto)** | Una clave secreta compartida utilizada para firmar el contenido (payload). Mantenla en privado. |
| **Trigger on (Disparar en)** | El subconjunto de eventos que deben disparar este webhook. |

Puedes configurar múltiples webhooks, cada uno escuchando un conjunto diferente de eventos.

## Eventos

| Evento | Se dispara cuando… |
| --- | --- |
| `content.created` | Una nueva entrada se guarda por primera vez. |
| `content.updated` | Una entrada existente se guarda (actualiza). |
| `content.published` | Una entrada cambia al estado de `published` (publicado). |
| `content.unpublished` | Una entrada previamente publicada deja de estar en estado `published`. |
| `content.deleted` | Una entrada se elimina (es movida a la papelera). |
| `content.restored` | Una entrada se restaura de la papelera. |

## Formato del payload (Carga útil)

Cada petición de webhook es un **POST** HTTP con `Content-Type: application/json`:

```json
{
  "event": "content.published",
  "occurred_at": "2025-05-03T12:00:00Z",
  "data": {
    "type": "posts",
    "id": "7K4p9xQ2mR",
    "slug": "mi-primer-post"
  }
}
```

- `event` — el nombre del evento de la tabla anterior.
- `occurred_at` — marca de tiempo (timestamp) ISO 8601 en UTC.
- `data.type` — el nombre de la colección (ej. `posts`, `pages`).
- `data.id` — el ID opaco y estable de la entrada afectada.
- `data.slug` — el slug seguro para URLs de la entrada afectada. Usa el `id` o el `slug` para obtener la entrada desde la API Pública.

::: tip Ligero por diseño
El payload contiene intencionalmente solo el evento y una referencia a la entrada. Usa la API Pública para recuperar la entrada completa si tu controlador (handler) necesita el contenido.
:::

## Verificación de firma

Cada petición incluye un encabezado `X-CometCMS-Signature`. Su valor es:

```
sha256=<HMAC-SHA256 hex digest>
```

La firma se calcula sobre **el cuerpo de la petición en crudo (raw request body)** utilizando el secreto de tu webhook como clave. Siempre verifica esta firma antes de procesar el evento.

### Node.js

```js
const crypto = require("crypto");

function verifySignature(rawBody, secret, signature) {
  const expected =
    "sha256=" +
    crypto.createHmac("sha256", secret).update(rawBody).digest("hex");
  return crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(signature));
}
```

### PHP

```php
function verifySignature(string $rawBody, string $secret, string $signature): bool {
    $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
    return hash_equals($expected, $signature);
}
```

### Python

```python
import hmac, hashlib

def verify_signature(raw_body: bytes, secret: str, signature: str) -> bool:
    expected = 'sha256=' + hmac.new(
        secret.encode(), raw_body, hashlib.sha256
    ).hexdigest()
    return hmac.compare_digest(expected, signature)
```

## Ejemplo: disparar un build en Netlify

1. En Netlify, ve a **Site settings → Build hooks** (Configuración del sitio → Hooks de construcción) y crea un nuevo hook. Copia la URL del hook.
2. En CometCMS, abre **Webhooks** y añade un webhook con:
   - **URL** — la URL de tu hook de Netlify
   - **Secret** — cualquier cadena aleatoria (Netlify no verifica las firmas, pero CometCMS enviará una de todas formas)
   - **Trigger on (Disparar en)** — `content.published`, `content.unpublished`
3. Publica o despublica un post — Netlify iniciará una nueva construcción automáticamente.