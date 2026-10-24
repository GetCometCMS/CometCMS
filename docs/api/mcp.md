# MCP API

The Model Context Protocol (MCP) endpoint lets AI coding assistants (GitHub Copilot, Claude Code, Cursor, etc.) interact with CometCMS directly from the editor. It exposes content types, entries, media, and permission-scoped page layouts as **tools** that the AI can discover and call on demand.

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
| Authentication    | Optional for public reads; Bearer token for writes and private reads (see [Access Tokens](../guide/api-tokens)) |
| Auth header       | `Authorization: Bearer YOUR_TOKEN_HERE`              |
| Non-POST requests | Return `405 Method Not Allowed`                      |

### Lifecycle

1. **Initialize** — the client calls `initialize` to negotiate the protocol version and discover server capabilities.
2. **Tool discovery** — the client calls `tools/list` to fetch available tool schemas.
3. **Tool calls** — the client invokes individual tools via `tools/call`.
4. **Notifications** — `notifications/initialized` without an `id` returns HTTP 202 with no JSON-RPC response. A `ping` request with an `id` returns an empty result.

Notifications and requests without an `id` field are treated as fire-and-forget and return `202 Accepted`.

---

## Authentication

Without a Bearer token, MCP operates in read-only mode. Initialization and tool discovery are available, and `tools/list` advertises only `comet_health`, `list_content_types`, `get_content_type`, `list_entries`, `get_entry`, `list_media`, and `get_media_item`. Only public schemas and published entries from collections with API exposure enabled (`api_enabled: true`), plus public media, can be read. Entry publication dates must have arrived. Private schemas and media are omitted from listings, and relations to private collections or unpublished entries are omitted. Pagination totals and media category counts reflect public results.

Write tools and direct reads of private resources require a valid Bearer token with appropriate permission grants. Calling a write tool without authentication returns a tool error with status `401`, even though it is omitted from discovery. Invalid supplied tokens are rejected with `401`; they do not fall back to anonymous access. Authenticated MCP is a management interface: a token with the required grants can access collections with API exposure disabled. Every expanded related entry requires its own `content.read` grant for the target collection, entry, workspace, and locale; a source entry grant does not authorize its targets. REST continues to require API exposure for authenticated and anonymous reads. Tokens are created in **Access Tokens** in the admin:

```http
Authorization: Bearer ctcms_…
```

| HTTP status | Meaning                                                     |
| ----------- | ----------------------------------------------------------- |
| `401`       | Invalid token, or token required for a write or private read. |
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
        "The token is valid but lacks permission. Update the token in Access Tokens or use a token that already has the required grant."
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
    "version": "1.1.0",
    "description": "CMS content, schema, media, and permission-scoped page-builder tools."
  }
}
```

---

## Page builder

Builder tools use the **same workspace endpoint**. They are advertised only to
bearer tokens with `site.manage` on `workspace:{workspace}:site:*`; direct calls
also enforce this permission. Headless tokens do not receive builder schemas.
There is no second MCP connection to configure.

These tools edit **content areas** and **templates** of a collection, by
`collection` and `variant` (`detail` or `index`). A template puts pages together
from a header, a content area and a footer; the first template whose conditions
match an entry is used and the last one (`default`) applies to everything else.
Layout tools take an optional `content` area id and default to the area the
default template shows. Changes affect every entry whose template shows that
area and take effect on published pages immediately. They do not create a separate design
draft. Entry-specific page fields use the existing entry tools. Editing the
header/footer themselves, reusable component definitions, theme and menus are
outside this tool group; choosing which header and footer a layout shows is not.

| Tool | Required inputs | Result |
| --- | --- | --- |
| `get_builder_schema` | none; optional `kind` | Kinds/children and limits, or props/styles/defaults/bindings for one kind. |
| `get_layout` | `collection`, `variant`; optional `content` | Ordered section summaries, node counts, revision, the content area and the templates that show it (`used_by_templates`). |
| `get_section` | target + `section_id` | One subtree with default props omitted, and revision. |
| `get_element` | target + `element_id` | One node's props/style, immediate child summaries, section ID and revision. |
| `set_layout` | target + `revision`, `sections` | Replace entire layout; returns compact overview and new revision. |
| `update_section` | target + `revision`, `section_id`, `patch` | Patch `name`, `props`, `style`, or replace `children`; returns overview and revision. |
| `update_element_props` | target + `revision`, `element_id`, `props`; optional `style` | Merge property/style keys; returns node and revision. |
| `list_templates` | `collection`, `variant` | Templates in order, content areas and the components that can be a header or footer. |
| `set_template_parts` | `collection`, `variant`; optional `template` (default `default`), `header`, `content`, `footer` | Header/footer: `default`, `none` or a section component id; content: a content area id. Returns the templates. |

Here `target` means `collection`, `variant` and optionally `content`. A section is any top-level node,
including a `page_content` placeholder or a section component reference.
Patches preserve omitted keys, descendants and other sections. Supplying
`children: []` removes children. Object-valued props (including localized maps)
replace the whole property: read them first to preserve other values/locales.
Reset a prop/style by supplying its schema default. Nodes use
`{id?, kind, name?, props?, style?, children?}`; IDs are generated when omitted.
Use `get_builder_schema(kind)` for valid properties and child placement.

All writes **require** a revision from a read. Stale edits return `409` with
instructions to reread, reconcile, and retry; the revision check and save share
the repository file lock. Invalid edits return `422` with the failing path and
schema discovery guidance, without saving a repaired/truncated layout. Builder
success responses provide `structuredContent` plus compact JSON text.

Example workflow (substitute the revisions returned by each call):

```json
{"name":"get_layout","arguments":{"collection":"pages","variant":"detail"}}
```

```json
{"name":"set_layout","arguments":{"collection":"pages","variant":"detail","revision":"<read revision>","sections":[{"id":"hero","kind":"section","children":[{"kind":"row","children":[{"kind":"column","children":[{"id":"title","kind":"heading","props":{"text":"Welcome","level":"h1"}}]}]}]}]}}
```

```json
{"name":"update_element_props","arguments":{"collection":"pages","variant":"detail","revision":"<set_layout revision>","element_id":"title","props":{"text":"Hello world"}}}
```

See the [design decision and full contract](../design/mcp-page-builder.md) for
endpoint rationale, update semantics and concurrency guarantees. Saves use
existing cache invalidation and restorable layout history, attributed to the
token principal. Preview and MCP history tools remain future work.

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

When `locale` matches one of the workspace languages, the entry's localized field values are returned for that locale.

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

<-- {"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-06-18","capabilities":{"tools":{"listChanged":false}},"serverInfo":{"name":"cometcms","title":"CometCMS","version":"1.1.0","description":"CMS content, schema, media, and permission-scoped page-builder tools."}}}
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

See [Access Tokens](../guide/api-tokens) for details on creating tokens and assigning permission grants.


## Localization

Languages and their default are configured on the workspace. All content types and the website use those workspace settings directly. Content type tools do not configure languages; returned `locales` and `default_locale` are read-only workspace values. Entry reads accept `locale` and return resolved content, `locale`, and a `revision`. `get_media_item` also accepts `locale` to resolve alternative text and captions. Anonymous reads only expose published variants in that language. Slugs, publication status, and publication dates are localized; stable IDs, authors, and shared fields stay shared.

Entry writes target the `locale` in the `entry` payload and preserve other languages and omitted fields. Include `revision` to detect stale writes (conflicts report status 409), and `reset_fields` to remove localized overrides. Pass ordinary values, not complete translation maps. A missing value inherits public default-language content; explicit empty values do not inherit. Locale and field permissions apply to updates, publication, and resets.

Field labels, descriptions, and select-option labels accept `{"type":"localized","values":{"en":"Name","de":"Name"}}`. Structural schema keys and option values remain shared. The website builder, components, menus, metadata, and forms use the same value model; the MCP design-tool surface is unchanged.
