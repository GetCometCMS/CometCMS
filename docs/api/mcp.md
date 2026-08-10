# MCP API

The Model Context Protocol (MCP) endpoint lets AI coding assistants (GitHub Copilot, Claude Code, Cursor, etc.) interact with CometCMS directly from the editor. It exposes content types, entries, and media as **tools** that the AI can discover and call on demand.

> MCP is a server-to-server protocol designed for AI tool calling. For traditional REST consumption from frontends or scripts, use the [Public API](./public-api) or [Admin API](./admin-api) instead.

---

## Base URL

```
https://yourdomain.com/mcp/v1/workspaces/{workspace}
```

The workspace slug is always part of the URL — there is no unscoped MCP endpoint.

---

## Protocol

CometCMS implements the [MCP specification](https://spec.modelcontextprotocol.io) (protocol version `2025-06-18`) over **JSON-RPC 2.0** with **JSON over HTTP POST**.

| Aspect            | Detail                                               |
| ----------------- | ---------------------------------------------------- |
| Transport         | HTTP POST, `Content-Type: application/json`          |
| Protocol          | JSON-RPC 2.0 + MCP tool primitives                   |
| Authentication    | Bearer token (see [API Tokens](../guide/api-tokens)) |
| Required header   | `Authorization: Bearer YOUR_TOKEN_HERE`              |
| Non-POST requests | Return `405 Method Not Allowed`                      |

### Lifecycle

1. **Initialize** — the client calls `initialize` to negotiate the protocol version and discover server capabilities.
2. **Tool discovery** — the client calls `tools/list` to fetch available tool schemas.
3. **Tool calls** — the client invokes individual tools via `tools/call`.
4. **Notifications** — `notifications/initialized` and `ping` are accepted but produce no response.

Notifications and requests without an `id` field are treated as fire-and-forget and return `202 Accepted`.

---

## Authentication

All tools require a valid Bearer token with appropriate permission grants. Tokens are created in **API-Tokens** in the admin:

```http
Authorization: Bearer cms_ct_abc123...
```

| HTTP status | Meaning                                                     |
| ----------- | ----------------------------------------------------------- |
| `401`       | Missing or malformed token.                                 |
| `403`       | Token is valid but lacks permission for the requested tool. |
| `404`       | Workspace or resource not found.                            |
| `422`       | Validation error — check parameter values and types.        |
| `500`       | Internal server error.                                      |

Error responses follow the JSON-RPC 2.0 error shape with additional context in `data`:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "error": {
    "code": -32000,
    "message": "Forbidden.",
    "data": {
      "status": 403,
      "details": { "code": "forbidden" },
      "required_permissions": ["content.read:my-workspace:content:pages:*"],
      "recovery": [
        "The token is valid but lacks permission. Update the token in API-Tokens or use a token that already has the required grant."
      ]
    }
  }
}
```

---

## Server Info

The `initialize` response advertises the server identity:

```json
{
  "protocolVersion": "2025-06-18",
  "capabilities": {
    "tools": { "listChanged": false }
  },
  "serverInfo": {
    "name": "cometcms",
    "title": "CometCMS",
    "version": "1.0.0",
    "description": "Headless CMS content, schema, and media tools."
  }
}
```

---

## Available Tools

### `comet_health`

Check that the CometCMS instance is reachable and report runtime capabilities.

| Parameter | Type | Required |
| --------- | ---- | -------- |
| _(none)_  |      |          |

**Required permission:** none (authenticated only).

**Response fields:** `ok`, `name`, `version`, `time`, `extensions.gd` (thumbnail support), `extensions.zip` (backup archive support).

---

### `list_content_types`

List all content type schemas in the workspace.

| Parameter | Type | Required |
| --------- | ---- | -------- |
| _(none)_  |      |          |

**Required permission:** `schema.read` on `schema:*`

---

### `get_content_type`

Fetch a single content type schema by its collection name.

| Parameter    | Type   | Required |
| ------------ | ------ | -------- |
| `collection` | string | ✅       |

**Required permission:** `schema.read` on `schema:{collection}`

---

### `create_content_type`

Create a new content type.

| Parameter        | Type             | Required |
| ---------------- | ---------------- | -------- |
| `name`           | string           | ✅       |
| `label`          | string           |          |
| `icon`           | string           |          |
| `fields`         | object           |          |
| `locales`        | array of strings |          |
| `default_locale` | string           |          |
| `singleton`      | boolean          |          |

**Required permission:** `schema.create` on `schema:{name}`

Field names may only contain letters, numbers, underscores, and dashes.

---

### `update_content_type`

Surgically update an existing content type. Omitted properties are **preserved** — this is not a full replacement.

| Parameter        | Type             | Required |
| ---------------- | ---------------- | -------- |
| `collection`     | string           | ✅       |
| `label`          | string           |          |
| `icon`           | string           |          |
| `fields`         | object           |          |
| `remove_fields`  | array of strings |          |
| `replace_fields` | boolean          |          |
| `locales`        | array of strings |          |
| `default_locale` | string           |          |
| `singleton`      | boolean          |          |

**Required permission:** `schema.update` on `schema:{collection}`

**Important:** By default, `fields` are merged into the existing schema (new keys added, existing keys overwritten). To delete fields, use `remove_fields`. To replace the entire field map, set `"replace_fields": true`.

---

### `delete_content_type`

Permanently delete a content type and all its entries.

| Parameter    | Type   | Required |
| ------------ | ------ | -------- |
| `collection` | string | ✅       |

**Required permission:** `schema.delete` on `schema:{collection}`

---

### `list_entries`

Fetch one paginated page of entries.

| Parameter    | Type            | Required |
| ------------ | --------------- | -------- |
| `collection` | string          | ✅       |
| `q`          | string          |          |
| `sort`       | string          |          |
| `include`    | string          |          |
| `locale`     | string          |          |
| `filters`    | object          |          |
| `limit`      | integer (1–100) |          |
| `offset`     | integer         |          |

| Default  | Value         |
| -------- | ------------- |
| `sort`   | `-created_at` |
| `limit`  | `20`          |
| `offset` | `0`           |

**Required permission:** `content.read` on `content:{collection}`

Use comma-separated relation names in `include` to expand relation fields inline (e.g. `"include": "author,category"`).

---

### `get_entry`

Fetch one entry by its stable ID or slug.

| Parameter    | Type   | Required |
| ------------ | ------ | -------- |
| `collection` | string | ✅       |
| `identifier` | string | ✅       |
| `include`    | string |          |
| `locale`     | string |          |

**Required permission:** `content.read` on `content:{collection}:{identifier}`

When `locale` matches one of the content type's configured locales, the entry's localized field values are returned for that locale.

---

### `create_entry`

Create a content entry.

| Parameter    | Type   | Required |
| ------------ | ------ | -------- |
| `collection` | string | ✅       |
| `entry`      | object | ✅       |

**Required permission:** `content.create` on `content:{collection}`

The `entry` object should contain the field values defined in the content type schema. To publish immediately, include `"status": "published"` (requires the `content.publish` permission grant).

---

### `update_entry`

Update a content entry by its stable ID or slug.

| Parameter    | Type   | Required |
| ------------ | ------ | -------- |
| `collection` | string | ✅       |
| `identifier` | string | ✅       |
| `entry`      | object | ✅       |

**Required permission:** `content.update` on `content:{collection}:{identifier}`

Only the fields included in `entry` are modified; omitted fields keep their existing values.

---

### `delete_entry`

Soft-delete a content entry by stable ID or slug (moves it to the trash).

| Parameter    | Type   | Required |
| ------------ | ------ | -------- |
| `collection` | string | ✅       |
| `identifier` | string | ✅       |

**Required permission:** `content.delete` on `content:{collection}:{identifier}`

---

### `list_media`

Fetch a compact overview of media files with category counts and one paginated page of results. Use `get_media_item` for full metadata and URLs.

| Parameter  | Type            | Required |
| ---------- | --------------- | -------- |
| `q`        | string          |          |
| `category` | string          |          |
| `limit`    | integer (1–100) |          |
| `offset`   | integer         |          |

**Required permission:** `media.read`

---

### `get_media_item`

Fetch one media file with URLs, dimensions, visibility status, and editable metadata.

| Parameter  | Type   | Required |
| ---------- | ------ | -------- |
| `filename` | string | ✅       |

**Required permission:** `media.read` on `media:{filename}`

---

### `create_media_category`

Create a media category or subcategory.

| Parameter | Type   | Required |
| --------- | ------ | -------- |
| `name`    | string | ✅       |
| `parent`  | string |          |

**Required permission:** `media.update`

If `parent` is provided, the category is created as a child subcategory. Category names are returned in the `meta.categories` array of the response.

---

### `set_media_category`

Assign or clear the category of a media file.

| Parameter  | Type   | Required |
| ---------- | ------ | -------- |
| `filename` | string | ✅       |
| `category` | string |          |

**Required permission:** `media.update` on `media:{filename}`

Omit `category` or pass an empty string to remove the file from its current category.

---

### `delete_media`

Delete a media file.

| Parameter  | Type   | Required |
| ---------- | ------ | -------- |
| `filename` | string | ✅       |

**Required permission:** `media.delete` on `media:{filename}`

---

## Example Session

### Initialize

```json
--> {"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18"}}

<-- {"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-06-18","capabilities":{"tools":{"listChanged":false}},"serverInfo":{"name":"cometcms","title":"CometCMS","version":"1.0.0","description":"Headless CMS content, schema, and media tools."}}}
```

### List tools

```json
--> {"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}

<-- {"jsonrpc":"2.0","id":2,"result":{"tools":[{"name":"comet_health","title":"CometCMS health","description":"Check the configured CometCMS public API health endpoint.","inputSchema":{"type":"object","properties":{},"additionalProperties":false}},{"name":"list_content_types","title":"List content types","description":"List CometCMS content type schemas.","inputSchema":{"type":"object","properties":{},"additionalProperties":false}}, ...]}}
```

### Call a tool

```json
--> {"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"list_content_types","arguments":{}}}

<-- {"jsonrpc":"2.0","id":3,"result":{"content":[{"type":"text","text":"{\n    \"data\": [\n        {\n            \"name\": \"pages\",\n            \"label\": \"Pages\",\n            \"icon\": \"file-text\",\n            \"fields\": {\n                \"title\": {\"type\": \"text\", \"required\": true},\n                \"body\": {\"type\": \"richtext\"}\n            }\n        }\n    ]\n}"}]}}
```

---

## Tool Discovery

AI assistants discover available tools automatically by calling `tools/list` during initialization. The server returns the full schema for each tool, including parameter names, types, descriptions, and required fields — no out-of-band configuration needed on the client side.

Each tool also declares MCP behavioral annotations:

- `readOnlyHint` identifies tools that do not modify workspace state.
- `destructiveHint` distinguishes overwriting or deleting operations from additive creates.
- `idempotentHint` identifies calls that have no additional effect when repeated with the same arguments.
- `openWorldHint` is `false` because the tools operate only on the configured CometCMS workspace and do not reach unrelated external systems.

These annotations are behavioral hints for MCP clients. Permission checks still apply to every tool call.

---

## Permission Reference

Every MCP tool maps to one or more permission grants. Below is a quick reference:

| Tool                    | Permission(s)                                     |
| ----------------------- | ------------------------------------------------- |
| `comet_health`          | _(authenticated only)_                            |
| `list_content_types`    | `schema.read`                                     |
| `get_content_type`      | `schema.read`                                     |
| `create_content_type`   | `schema.create`                                   |
| `update_content_type`   | `schema.update`                                   |
| `delete_content_type`   | `schema.delete`                                   |
| `list_entries`          | `content.read`                                    |
| `get_entry`             | `content.read`                                    |
| `create_entry`          | `content.create` (+ `content.publish` to publish) |
| `update_entry`          | `content.update` (+ `content.publish` to publish) |
| `delete_entry`          | `content.delete`                                  |
| `list_media`            | `media.read`                                      |
| `get_media_item`        | `media.read`                                      |
| `create_media_category` | `media.update`                                    |
| `set_media_category`    | `media.update`                                    |
| `delete_media`          | `media.delete`                                    |

See [API Tokens](../guide/api-tokens) for details on creating tokens and assigning permission grants.
