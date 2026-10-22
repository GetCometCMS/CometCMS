# MCP page-builder tools

## Decision

Keep the workspace MCP endpoint. Implement builder tools as an internal module,
sharing authentication, workspace isolation, transport and errors with content
and media tools. Discovery includes builder tools only for bearer tokens allowed
`site.manage` on `workspace:{workspace}:site:*`. Execution checks the same grant,
including direct calls to undisclosed tools. Headless tokens therefore carry no
builder tool schemas. A second endpoint is unnecessary unless deployment or
authentication needs to differ; it can reuse this module later.

MCP already supports tool discovery and structured results with a JSON text
fallback: https://modelcontextprotocol.io/specification/2025-06-18/server/tools.

## Scope and granularity

This increment edits shared content-type layouts (`collection`, `variant` =
`detail` or `index`). They affect every entry using that layout, immediately on
published pages. Entry-specific page fields remain content, edited through
`get_entry` / `update_entry`; regions, reusable component definitions, theme,
menus, preview and history tools are separate future extensions. Component
instances are references, never expanded into editable definition nodes.

A section means one top-level layout node: normally a section, but also a
`page_content` placeholder or section component. IDs remain stable. Prefer a
layout overview, then reading and updating one section or element. Never require
an agent to round-trip a full layout to change a heading.

## Contract (before implementation)

All responses use `{data: ...}` as structured content and serialized JSON text.
Errors have `isError: true`, status, path/code details and recovery instructions.
Every layout response identifies collection, variant and whole-layout revision.

| Tool | Inputs | Output / granularity |
| --- | --- | --- |
| `get_builder_schema` | optional `kind` | Without kind: kinds and allowed children, limits, variants. With kind: that kind's props, allowed styles, defaults, bindings and child kinds. |
| `get_layout` | `collection`, `variant` | Ordered section summaries (`id`, `kind`, optional `name`, node count), revision, shared/live effect and next step. No props or raw editor tree. |
| `get_section` | target + `section_id` | One complete section subtree with default props omitted, revision. |
| `get_element` | target + `element_id` | One node's props/style/name plus child ID/kind summaries, enclosing section ID, revision. Can also inspect structural nodes. |
| `set_layout` | target + `revision`, `sections` | Explicit full replacement, including empty list. Compact resulting layout overview and new revision. |
| `update_section` | target + `revision`, `section_id`, `patch` | Partial patch of `name`, `props`, `style`, or explicit replacement `children`. Other sections and omitted keys preserved. Returns compact overview and new revision. |
| `update_element_props` | target + `revision`, `element_id`, `props`, optional `style`, `responsive`, `states` | Partial property/style update of any node by ID, preserving children. Returns changed node (no descendant trees) and new revision. |

`target` is `collection`, `variant`. Nodes use the existing
`{id?, kind, name?, props?, style?, responsive?, states?, children?}` vocabulary.
`responsive` (`{tablet?, mobile?: {props?, style?}}`) and `states`
(`{hover?, focus?: {props?, style?}}`) hold values that differ on smaller
screens or while hovered or focused; `get_builder_schema(kind)` lists the keys
each accepts, and a patch replaces the whole map. Omitted IDs are
server-generated; supplied IDs must be unique and valid. Property/style maps
merge at the property-key level: supplying an object-valued property replaces
that property, including localized maps. Read it first to preserve translations.
Use schema defaults to reset props/styles. Empty `children` explicitly removes
children. Nodes cannot change kind or ID through a patch; replace that subtree
through `children` or `set_layout` instead.

## Validation and concurrency

Validate shapes and unknown keys, then compare the submitted values and tree
against `LayoutSchema::normalize`. Adding defaults and IDs is allowed; dropping
nodes, repairing supplied IDs, clamping values, discarding keys or sanitizing
submitted values is rejected with a path and schema-read instruction. Never
report success after silently losing part of a proposal. Style defaults may be
omitted in persisted trees. Bound/compound values may omit normalized defaults,
but invalid supplied members must not disappear. Validation is bounded by the
schema's node and nesting limits.

All writes require a nonempty whole-layout revision. Read, patch and validate a
snapshot, then call `SiteRepository::saveLayout` with that revision. Its existing
file lock checks the current layout hash and writes within one critical section,
so even a change occurring after validation fails with 409. The revision covers
all sections deliberately: a safe, conservative starting point. On conflict,
read the affected section/layout again, reconcile intent and retry with its new
revision. Never automatically retry a stale replacement. The admin uses the same
repository concurrency mechanism; MCP also detects intervening admin saves.
Generated defaults already have deterministic IDs and stable revisions.

Saves retain repository cache invalidation and revision history, attributed to
the access token principal. Discovery filtering is convenience; permission checks
at execution remain authoritative.

## Verification

Exercise discovery and permission denial, schema discovery, creation with IDs,
section and element reads, partial props/style edits, structural replacement,
invalid input with no writes, explicit empty layouts, stale saves (including an
intervening repository/admin save), stable defaults and workspace isolation.
