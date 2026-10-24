# Ticket: Templates replace layouts

## Goal

Replace per-type layouts (`theme/layouts/{type}/{variant}.json`) with
**templates**: first-class page builder documents that own a page's whole
structure (frame + body). Templates are the only system after this
change. There are no layouts next to them.

What this enables:

- Several templates per content type (e.g. "Article", "Long-read", "Landing").
- Editors pick a template per entry, or rules pick one.
- Every page kind (list, entry, 404, search) is a template, so "everything is
  a template" is the one mental model.

## Starting point (already built)

- **Frame assignment** (`theme/frames.json`, see `SiteRepository::frames()`,
  `SiteContext::frameNodes()`): per type × variant, header/footer are
  `default` (the global region), `none` or a section component id. The builder
  inspector has an Assign control. The "Templates" tab under
  Design & layout shows a page wireframe per type/variant. Frame assignment
  becomes a property of each template. Migrate `frames.json` into templates
  and delete it.
- Per-entry page content: `page` field + `page_content` element (value
  keyed by field, `{format_version, nodes}`).
- Components with editable props/overrides, used for sharing pieces between
  templates.
- Node-level visibility exists only as `hide_on` (devices). There are no field
  conditions yet.

## Fixed decisions

1. **Templates replace layouts.** Each existing `layouts/{type}/{variant}.json`
   becomes that type's default template for the variant, with its frame from
   `frames.json`. Generated (never customized) layouts stay generated: a
   type without a stored template uses the generated default.
2. **No template inheritance.** Share structure through components.
3. **Deterministic resolution, no priority numbers.** For a page:
   1. template picked on the entry (detail pages only), if it still exists and
      belongs to the type + variant
   2. first matching rule-based template of the type + variant, most specific
      rule first (ties: fixed sort order shown in the UI)
   3. the type's default template for the variant
   4. the generated default
   The builder and the entry edit screen always show which template is in
   effect, and why.
4. **Small rule language.** Rules are post type + variant (implicit), plus
   optional field conditions (`field` `equals|not_equals|empty|not_empty`
   `value`). Nothing else in this ticket.
5. **Existing sites render byte-identical** after the migration (test with a
   fixture site: stored layouts, customized regions, frames, page fields).

## Storage

```
theme/templates/{id}.json
{
  "format_version": 1,
  "id": "blog-article",
  "name": "Article",               // editor-only, plain string like component names
  "type": "blog",
  "variant": "detail",             // detail | index
  "default": true,                 // exactly one default per type+variant
  "rules": [],                     // field conditions, AND-ed
  "frame": { "header": "default", "footer": "default" },
  "nodes": [ ... ]                 // same node tree as layouts today
}
```

- Revisions per template (as layouts have today: `revisions/site/templates/{id}`).
- Entry choice: store `template` (id) on the entry as a system property, not
  a field. It is empty by default (= resolution steps 2–4).
- `page` field values stay keyed by field. With several templates, the entry
  edit screen lists page fields placed in the **active** template. Switching
  templates never deletes page field data. Fields not shown in the new
  template stay stored and come back when switching back.

## Migration

- On first read (lazy, like region/layout upgrades), or through an explicit
  upgrade step in `SiteRepository`: create one default template per stored
  layout, merge in its `frames.json` entry, keep the layout's revisions
  readable under the template, then remove `layouts/` and `frames.json`.
- Backups/restore (`BackupService`, `RestoreService`) handle both old and new
  formats. Restoring an old backup migrates.
- `componentUsages`, `formUsages`, `deleteComponent` detaching, `forgetType`
  and `needsLayoutUpgrade` move from layouts to templates.

## Admin UI

- **Templates tab** (Design & layout): the type dropdown and wireframe stay.
  Add a list of the type's templates per variant (default marked) with
  New / Duplicate / Rename / Delete / Set as default. Clicking the content box
  opens the template in the builder. Rules editor per template (field
  conditions only).
- **Builder**: the route becomes `/page-builder/builder/template/:id`. Keep the
  old `layout/:type/:variant` routes as redirects to the type's default
  template. The page switcher lists the type's templates. The inspector's
  header/footer Assign control writes the template's `frame` and is saved with
  the template (part of the builder draft and undo history).
- **Entry edit screen**: a "Template" select (Automatic + the type's detail
  templates) showing which template is in effect. "Design in page builder"
  opens the page content inside the active template.
- **Delete a template**: entries that picked it fall back to automatic
  (warn with a count first). The default template cannot be deleted.

## API / MCP

- Admin API: `/admin/api/site/templates` CRUD + revisions, replacing the
  layout endpoints (keep layout endpoints as thin aliases to the type's default
  template for one release).
- Preview: `target: template` with `id`, plus `entry` for detail templates.
- MCP builder tools (`PageBuilderTools.php`, `docs/design/mcp-page-builder.md`):
  take `template` instead of `collection` + `variant`, and add `list_templates`.
  `collection` + `variant` keep working and resolve to the default template.
  Enforce `index_enabled` as today.
- OpenAPI (`docs/public/api/openapi.yaml`) and `docs/guide/website.md` updated.

## Phasing inside this ticket

1. Template store + migration + resolution with default templates only. No
   visible behaviour change; all existing tests pass.
2. Several templates per type, entry template picker, Templates tab list.
3. Rules (field conditions) with the "why this template" display.

Each phase is its own commit and keeps the test suite green.

## Acceptance criteria

- A site with stored layouts, frames and page content renders identically
  before and after the migration (fixture test).
- A type can have several detail templates. An entry can pick one, and the
  page renders with that template's frame and nodes.
- A rule-based template applies to matching entries without a picked template.
  The UI shows why.
- Deleting a template or component never breaks a page: fallbacks render and
  usages are listed before deleting.
- MCP tools work with template ids and with the old collection/variant inputs.
- `make test` (PHP + web unit tests) and the browser tests pass.

## Out of scope

- Template inheritance, nested templates.
- Node-level `visible_if` conditions (separate ticket, after phase 3).
- Non-section frames (sidebars). The frame stays header + footer.
