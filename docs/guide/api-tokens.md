# Tokens de API

Los tokens de API permiten que aplicaciones externas (generadores de sitios estáticos, scripts de despliegue, aplicaciones móviles, etc.) accedan a la [API Pública](../api/public-api) sin necesidad de una sesión de usuario.

## Crear un token

1. Ve a **Tokens de API (API-Tokens)**.
2. Haz clic en **Nuevo token (New token)**.
3. Dale un nombre al token (ej. `Script de despliegue`), una descripción opcional y asigna los permisos (grants).
4. Haz clic en **Crear token (Create token)**.
5. **Copia el token inmediatamente** — solo se muestra una vez.

## Permisos (Grants)

Los tokens utilizan el mismo formato de permisos que los roles. Un token se inicia sin permisos implícitos de rol; solo puede hacer lo que sus permisos explícitos permiten.

```json
[
  {
    "effect": "allow",
    "actions": ["content.read", "content.update"],
    "resources": ["content:pages:homepage"],
    "fields": ["hero_title", "hero_image"]
  }
]
```

Cada permiso tiene:

| Propiedad | Descripción |
| --- | --- |
| `effect` | `allow` (permitir) o `deny` (denegar). Un `deny` coincidente anula los `allow` coincidentes. |
| `actions` | Uno o más nombres de acciones. Usa `*` solo para acceso administrativo total. |
| `resources` | Uno o más patrones de recursos. Se admiten comodines `*`. |
| `fields` | Lista de campos de contenido permitidos opcionales para operaciones de creación/actualización. |
| `conditions` | Restricciones opcionales. Las condiciones soportadas son `own` (propio), `status` (estado) y `locales` (idiomas). |

Las acciones de contenido comunes son `content.read`, `content.create`, `content.update`, `content.publish`, `content.delete`, `content.restore`, `content.revisions.read` y `content.revisions.restore`.

Las acciones de esquema son `schema.read`, `schema.create`, `schema.update` y `schema.delete`.

Las acciones de medios son `media.read`, `media.upload`, `media.update` y `media.delete`.

Las acciones de la interfaz de administración incluyen `dashboard.read`, `activity.read`, `profile.read`, `profile.update`, `users.read`, `users.create`, `users.update`, `users.delete`, `tokens.read`, `tokens.create`, `tokens.revoke`, `roles.read`, `roles.create`, `roles.update`, `roles.delete`, `backups.read`, `backups.create`, `backups.restore`, `backups.delete`, `webhooks.manage`, `updates.read`, `updates.check`, `updates.download` y `updates.install`.

Los recursos comunes incluyen `content:*`, `content:posts:*`, `content:pages:homepage`, `schema:*`, `schema:posts`, `media:*` y `media:category:brand-assets`. Los permisos específicos por espacio de trabajo prefijan esos mismos recursos con `workspace:{workspace}:`, por ejemplo `workspace:site-a:content:posts:*`.

Formatos de recursos:

| Área | Formato | Ejemplos |
| --- | --- | --- |
| Contenido | `content:{colección}:{entrada}` | `content:posts:*`, `content:pages:homepage` |
| Esquema | `schema:{tipo-de-contenido}` | `schema:*`, `schema:posts` |
| Medios | `media:*`, `media:{archivo}` o categoría | `media:*`, `media:hero.jpg`, `media:category:Marca / Logos` |
| Espacios de trabajo (Workspaces) | `workspace:{espacio}:{recurso}` | `workspace:site-a:content:posts:*`, `workspace:site-a:media:*` |
| Usuarios | `users:{id}`, `tokens:{id}` o roles | `users:*`, `tokens:*`, `roles:*` |
| Sistema | Recurso de sistema nombrado | `dashboard:*`, `activity:*`, `backups:*` |

Ejemplos de condiciones:

```json
[
  {
    "effect": "allow",
    "actions": ["content.update"],
    "resources": ["content:posts:*"],
    "fields": ["title", "summary", "body"],
    "conditions": {
      "own": true,
      "status": ["draft", "protected"],
      "locales": ["en", "es"]
    }
  }
]
```

La papelera, copia de seguridad/restauración, configuraciones, usuarios y la gestión de tokens no están expuestos a través de la API de tokens públicos.

## Usar un token

Pasa el token como un **Token Bearer** en el encabezado `Authorization`:

```http
GET /api/v1/workspaces/site-a/content/posts
Authorization: Bearer TU_TOKEN_AQUI
```

## Revocar un token

Haz clic en **Revocar (Revoke)** junto al token en la página de API-Tokens. Los tokens revocados no pueden ser usados y la acción no se puede deshacer (no se pueden des-revocar).