# Ticket: Templates assemble pages from parts

> **Status:** implemented 2026-10-24 on `feat/add-template-builder`, all three
> phases. Differences from the plan below:
>
> - **Changed 2026-10-24: each template owns its content area.** Shared,
>   named content areas were dropped: they made an edit in one template change
>   others unnoticed. A template has no `content` part any more; its content
>   is stored at `content/{type}/{variant}/{template id}.json`. New templates
>   are created on the server right away (`POST /admin/api/site/templates/{type}/{variant}`,
>   body `{name, from, parts}`) as a copy of `from` (default: the default
>   template), so their content can be designed at once. A template left out
>   of a saved list is deleted with its content and history. Older workspaces
>   migrate lazily: an area moves (with history) to the first template that
>   showed it, other templates get a copy, unused areas are removed.
> - Content is served by the layout endpoints with a `template` parameter
>   (`/admin/api/site/layouts/{type}/{variant}?template=…`); templates are
>   saved with `PUT /admin/api/site/templates`. MCP layout tools take `template`.
> - The builder keeps its route `/page-builder/builder/layout/:type/:variant`
>   with `?template=` (the template whose content is edited). Without it, it
>   opens the entry's template, else the default. There is no "preview with
>   another template" any more: opening another template edits its content.
> - An entry's chosen template is the system property `page_template`
>   (reserved as a field key).

## Goal

Replace per-type layouts with **templates**. A template holds no nodes of its
own. It is a small record that puts a page together from **parts** and says
when it applies:

```
Template "Blog – featured"
  applies to:  blog · detail (entry pages)
  conditions:  featured = true
  header:      Promo header        (a header part)
  content:     Blog long-read      (a content area of blog · detail)
  footer:      Site footer         (a footer part)
```

Templates are the only system after this change. Per-type layouts and
`frames.json` are migrated and then removed.

## Decisions (approved 2026-10-24)

1. **Parts:**
   - **Header and footer parts** are section components (shared or bound to a
     type, see `SiteRepository::fitsFrame()`). The site's own header and footer
     (`regions/header.json`, `regions/footer.json`) are two more parts in the
     same pickers, named "Site header" and "Site footer". A template can also
     choose **none**.
   - **Content areas** belong to one type + variant, because they show that
     type's fields. Today's single layout per type + variant becomes a named
     list of content areas ("Blog long-read", "Blog compact"). This is the only
     real storage change for node trees.
2. **Resolution is first match in a visible order.** Each type + variant has
   an ordered list of templates. The first one whose conditions match wins.
   Its last entry is the **default template**: it always matches, cannot be
   deleted, and has no conditions. Reorder by dragging. No priority numbers,
   no "most specific wins".
3. **Conditions only on entry pages.** List pages (`index`) have no entry, so
   their templates have no conditions. Order alone decides (in practice: the
   default). URL or page-number conditions are out of scope.
4. **Entry template pick (later phase).** An entry may force a template of its
   type. That pick comes before conditions. Empty = automatic.
5. **Page fields.** Different content areas can place different `page`
   fields. An entry keeps all its page field data. The edit screen lists the
   fields placed in the content area that applies to it now. Switching
   templates never deletes data.
6. **No template inheritance.** Share structure through components.

## Storage

```
theme/
  content/{type}/{variant}/{id}.json   content areas: {format_version, id, name, nodes}
  templates/{type}/{variant}.json      ordered templates of one page kind:
  {
    "templates": [
      { "id": "featured", "name": "Blog – featured",
        "conditions": [ { "field": "featured", "op": "equals", "value": true } ],
        "header": "promo-header", "content": "long-read", "footer": "default" },
      { "id": "default", "name": "Default",
        "conditions": [], "header": "default", "content": "default", "footer": "default" }
    ]
  }
```

- `header` / `footer`: `"default"` (site region), `"none"`, or a component id.
- `content`: a content area id of the same type + variant.
- Conditions are AND-ed. Operators: `equals`, `not_equals`, `empty`,
  `not_empty`, `contains` (for select/multi-value fields). Allowed fields: the
  type's own fields and `slug`; date conditions are out of scope. Validation
  rejects unknown fields and operators that don't fit the field type.
- The **Entry is equal to** condition uses `{"field":"uid","op":"equals","value":["entry-uid", "another-uid"]}`.
  Select entries of the template's content type; any selected UID matches.
  UIDs stay stable when titles or localized slugs change. An empty selection
  matches no entries. Other conditions still all need to match.
- Templates have no revisions of their own (they are small). Content areas
  keep the revision history layouts have today
  (`revisions/site/content/{type}/{variant}/{id}`).
- A type without a stored template list behaves as one default template with
  the generated content area and the site header/footer, like today.

## Resolution

`SiteContext::template(type, variant, entry)` returns
`{template, header, content, footer, reason}`:

1. entry's picked template (phase 3), if it exists in this list
2. first template whose conditions match the entry (detail only)
3. the default template

`reason` is a short machine value (`picked`, `conditions`, `default`) plus the
matched conditions, for the UI below. Missing parts fall back: a deleted
component → site header/footer, a missing content area → the default
template's content area (→ generated).

`PageRenderer` renders the resolved parts. `frameNodes()` from the current
frame work becomes the header/footer part of this.

## Migration

On first read (lazy, like the region/layout upgrades):

- each stored `layouts/{type}/{variant}.json` → content area `default` named
  "Default", with its revisions moved
- each type + variant → a template list with one default template. Its
  header/footer come from `frames.json`.
- then delete `layouts/` and `frames.json`

Requirements:

- A fixture site (stored layouts, customized regions, frames, page fields,
  components) renders **byte-identical** before and after.
- Backups made before the change restore and migrate.
- `componentUsages`, `formUsages`, `deleteComponent` (detach + forget
  assignments), `forgetType`, and the upgrade checks move to content areas and
  templates.

## Admin UI

### Templates tab (Design & layout)

It replaces the frame-only outline built on 2026-10-24
(`SiteTemplatesPanel.vue`).

- Type select + entry/list switch (as today).
- Left: the ordered template list with drag handles, "Default" pinned last,
  and New / Duplicate / Rename / Delete.
- Right: the selected template as the page outline (header / content /
  footer boxes with theme-colored hover). Clicking a box opens a picker:
  - header/footer: Site header, None, section components, "Edit …"
  - content: the type's content areas, "New content area", "Edit in builder"
- Below the outline: the condition editor (field, operator, value rows; hidden
  for list pages and for the default template).

### Page builder

- **Target:** the builder edits a **content area**:
  `/page-builder/builder/content/:type/:variant/:id`. The old
  `layout/:type/:variant` routes redirect to the area used by the type's
  default template.
- **Canvas:** the header and footer around the canvas are the parts of the
  template being previewed. That is the template the sample entry resolves to,
  or one chosen in the template switcher (below).
- **Page dropdown (`BuilderPageSwitcher.vue`):** group the "Pages" section by
  type. Under each type list its templates. Each template shows its three
  parts as sub-items (Header: Promo header · Content: Blog long-read ·
  Footer: Site footer), and each part opens that part in the builder. Also add
  a "Content areas" group per type and keep the existing Regions and
  Components groups. The current part is marked as now.
- **Template indicator:** the builder top bar shows "Template: Blog –
  featured" next to the page title, with the reason ("matches featured =
  true", "default", "picked on this entry"). Clicking it opens a menu: switch
  the template being previewed, plus "Edit template" (→ Templates tab with
  that template selected).
- **Inspector:** selecting the header/footer shows the current part and the
  Assign select (existing). It now writes the previewed template's
  `header`/`footer` and states which template it changes ("Applies to:
  Blog – featured"). Saved with Save and part of undo, like today's frames.

### Preview admin bar (`PreviewAdminBar.php`)

Add a **Template** dropdown (same `details.c-admin-dropdown` pattern as the
account menu) after the status:

- Summary: the template name ("Blog – featured").
- Panel: the reason (why this template applies), then the three parts with
  their names. Each one links to its builder (header/footer region or
  component, content area with this entry as sample), plus "Edit template"
  linking to the Templates tab.
- Shown only with `site.manage`. Same German/English texts as the rest of the
  bar.

### Entry edit screen (phase 3)

- "Template" select: Automatic (shows which one applies and why) + the
  type's detail templates.
- "Design in page builder" opens the page content inside the applying
  template.

## API / MCP

- Admin API: `/admin/api/site/templates/{type}/{variant}` (GET/PUT the ordered
  list, with revision hash), `/admin/api/site/content/{type}/{variant}` (list,
  create) and `/{id}` (GET/PUT/DELETE + revisions). The layout endpoints stay
  one release as aliases of the default template's content area.
- Preview: `target: content` (type, variant, id, entry, optional `template`
  to preview with), and `templates` drafts in the preview body (as `frames`
  works today).
- MCP: `get_layout` / `set_layout` / `update_*` take an optional `content` id
  (default: the default template's area). `set_layout_frame` becomes
  `set_template_parts` (template id + header/content/footer). Add
  `list_templates`. Update `docs/api/mcp.md`, `docs/design/mcp-page-builder.md`
  and `docs/public/api/openapi.yaml`.

## Phases (one commit each, suite green after each)

1. **Storage, migration and resolution.** Content areas plus template lists
   with one default template per page kind. The builder edits content areas.
   The Templates tab shows the default template (behaviour matches today).
   The fixture test proves byte-identical output.
2. **Several templates and content areas.** Ordered list, conditions,
   resolution with `reason`, builder template indicator and switcher, page
   dropdown grouping, preview admin bar dropdown.
3. **Entry template pick.** Entry screen select, picked-first resolution.

## Acceptance criteria

- Existing sites render identically after migration.
- A type can have several templates. A featured blog post renders with the
  "featured" template's header, content area and footer. Other posts get the
  default.
- The builder shows which template and parts the canvas shows and why, and
  every part is reachable from the page dropdown.
- The preview admin bar lists the template and its parts with edit links.
- Deleting a component or content area never breaks a page. Fallbacks
  render, and usages are listed before deleting.
- MCP tools work with and without the new `content` argument.
- `npm test` and the browser tests pass.

## Out of scope

- Template inheritance or nesting.
- Node-level `visible_if` conditions (separate ticket).
- Frames other than header/footer (sidebars).
- Conditions on list pages, URLs or dates.
