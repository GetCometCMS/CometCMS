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

The tools edit a template's content area (`collection`, `variant` =
`detail` or `index`, optional `template`), which affects every entry using that
template immediately on published pages, or with `entry` (and optional `area`)
one entry's own content in an Entry content area its template shows. Both
scopes share reads, patches, validation and revisions, so an agent is never sent
to a write path without them: `update_entry` / `create_entry` refuse page
fields. Regions, reusable component definitions, theme, menus, preview and
history tools are separate future extensions. Component
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
| `get_builder_schema` | optional `kind` (one or a list) | Without kind: kinds and allowed children, every style key's values once (equal values grouped), icon names, limits, variants. With kind: props, defaults, bindings, child kinds and the names of its style keys, so shared values are never repeated per kind. |
| `get_layout` | target; optional `section_ids`, `depth`, `mode` | Ordered section summaries (`id`, `kind`, optional `name`, node count) by default. `depth` or `mode` opts into trees; `section_ids` filters top-level roots in layout order. Whole-layout revision, shared/live effect and next step. |
| `get_section` | target + `section_id`; optional `depth`, `mode` | One section subtree with default props omitted by default; bounded/summary reads available. Whole-layout revision. |
| `get_element` | target + `element_id` | One node's props/style/name plus child ID/kind summaries, enclosing section ID, revision. Can also inspect structural nodes. |
| `set_layout` | target + `revision`, `sections` | Explicit full replacement, including empty list. Compact resulting layout overview and new revision. |
| `update_section` | target + `revision`, `section_id`, `patch` | Partial patch of `name`, `props`, `style`, or explicit replacement `children`. Other sections and omitted keys preserved. Returns the section summary and new revision. |
| `update_element` | target + `revision`, `element_id`, at least one of `props`, `style`, `responsive`, `states` | Partial property/style update of any node by ID, preserving children. Returns changed node (no descendant trees) and new revision. |

`target` is `collection`, `variant` and `template`, or `entry` and `area`. Nodes use the existing
`{id?, kind, name?, props?, style?, responsive?, states?, children?}` vocabulary.
`responsive` (`{tablet?, mobile?: {props?, style?}}`) and `states`
(`{hover?, focus?: {props?, style?}}`) hold values that differ on smaller
screens or while hovered or focused; `get_builder_schema(kind)` lists the keys
each accepts, and a patch replaces the whole map. Common state keys are listed in
`states.style` and `states.props`; additional state-specific keys are listed in
`states.variants[state]`. Hover supports independent `animation`,
`animation_duration`, and `animation_delay` values in `states.hover.style`,
alongside normal load/scroll animation settings. Omitted IDs are
server-generated; supplied IDs must be unique and valid. Property/style maps
merge at the property-key level: supplying an object-valued property replaces
that property, including localized maps. Read it first to preserve translations.
Use schema defaults to reset props/styles. Empty `children` explicitly removes
children. Nodes cannot change kind or ID through a patch; replace that subtree
through `children` or `set_layout` instead.

Read `depth` counts child levels below each section root (`0`: roots only).
Omitting depth includes all descendants. At a cut, nonempty children become
`child_count` and `children_truncated: true`. `mode: summary` includes IDs, kinds,
names and stored styles (including responsive/state style overrides), omitting
all props. `mode: full` retains nondefault props and complete overrides. A bare
`get_layout` remains an overview; either explicit mode or depth requests a tree.
Section selectors must be nonempty and unique; unknown IDs fail with 404.
Filtering never changes the revision or the layout being edited. Partial reads
must not be round-tripped as full replacements.

## Validation and concurrency

Validate builder arguments against the advertised input schema before resolving
layout scope. Shape errors use the same guiding 422 tool result as semantic
validation, with tool, path, expected schema, received type and a valid example
where supplied. Patch schema descriptions list members, types and an example so
hosts can also explain preflight failures. A host that rejects a request before
it reaches CometCMS owns that error's presentation; server validation cannot
change it. Malformed argument envelopes are rejected for all tools.

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
