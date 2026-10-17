# Website

CometCMS is headless first, but each workspace can also publish its content as a website. Pages are rendered by PHP on your server, so there is still nothing to install: no Node, no build step and no JavaScript in the pages themselves. The API keeps working whether the website is on or off.

The website is data-driven. You design a **layout per content type** once, and every entry of that type uses it. Editors keep filling in fields; the layout decides how those fields look.

## Turning it on

Open **Website** in the admin sidebar (administrators only, permission `site.manage`) and switch on **Publish website** in the **Overview** tab. Under **Content types → edit**, turn on **Show content as frontend pages** in the base settings card. The **Frontend pages** card then appears with URL, search engine and layout settings:

| Setting       | Example         | Description                                                       |
| ------------- | --------------- | ----------------------------------------------------------------- |
| Entry URL     | `/blog/{slug}`  | Where each entry lives. Collections need exactly one `{slug}`.    |
| Page URL      | `/about`        | The fixed address of a single page.                               |
| List page URL | `/blog`         | Optional page listing all entries of a collection, with paging.   |

`/admin`, `/api`, `/mcp`, `/media`, `/media-thumbs`, `/_site` and `/site-preview` are reserved and cannot be used. **Private** content types are never published, and only `published` (or due `scheduled`) entries are visible — exactly like the public API.

The **Frontend pages** column in the content types table shows whether pages are configured for each type, even when the website is off. Users with `site.manage` can change this setting in the type’s base settings; other users see its status. Private types cannot enable public frontend pages.

The homepage can be a single page, one entry, or a collection's list page. Until you choose one, CometCMS shows an automatic overview with the newest entries of every published collection.

## Layouts and the builder

The **Content types** table shows which types have frontend pages enabled. Open a content type’s **Frontend pages** card to configure its URLs and edit its layouts. Every content type has up to three layouts:

- **Page** — a single entry.
- **Card** — how an entry looks inside lists, related-entry fields and entry lists elsewhere.
- **List page** — the page at the list URL.

Layouts are generated from the content type's fields until you change them, so a new type looks reasonable immediately.

The builder shows a live preview rendered by the same code as the real site. Layouts are built from **sections → rows → columns → elements**:

| Element      | Shows                                                                         |
| ------------ | ----------------------------------------------------------------------------- |
| Field display | Specialized values such as galleries, repeaters, related cards, badges, or meters. |
| Heading      | Static text, field references, or a mixture of both.                           |
| Text         | Rich text.                                                                    |
| Image        | An image from the media library or a bound media field.                        |
| Button       | A link to this entry, another entry, a list page, the homepage or any URL.    |
| Entry list   | Entries of any public collection, filtered, sorted and optionally paged.     |
| Menu         | One of the menus from the **Menus** tab.                                      |
| Site title   | Logo, title and tagline from the website settings.                            |
| Languages    | Links to the current page in the other website languages.                     |
| Spacer, Divider | Space and separators.                                                      |

Repeater fields contain an **item template**: the elements inside them are repeated for every row and bind to the repeater's sub-fields.

Drag elements from **Add** onto the preview or into the **Outline**, or click them to insert next to the selection. Select an element to change its content and style in the inspector. Style options come from the theme (sizes, colors, radius, shadows), which keeps every page consistent. Shortcuts: <kbd>Ctrl</kbd>+<kbd>Z</kbd> undo, <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>Z</kbd> redo, <kbd>Ctrl</kbd>+<kbd>D</kbd> duplicate, <kbd>Del</kbd> delete, <kbd>Ctrl</kbd>+<kbd>S</kbd> save.

If a field is renamed or removed, elements bound to it render nothing and the builder marks them.

### Responsive column order

Select a **row** and enable **Reverse columns on mobile** and/or **Reverse columns on tablet** in the content inspector. Each setting applies independently: mobile is below 768 px, tablet is 768–1023 px, and desktop keeps the original order. All columns are reversed, including rows with more than two columns or columns that wrap onto multiple lines. This changes the visual order; keyboard and screen-reader order still follows the layout tree.

### Background image blur

Select a **section**, choose a **Background image**, then set **Background blur (px)** from 0 to 40. Zero keeps the image sharp. The effect applies to a separate image layer, so headings, buttons, menus and overlays stay sharp. Image size, focal point and repeat options continue to apply. The image extends slightly behind the clipped edges to avoid a faded border when blurred.

### AI and MCP access

The builder stores layouts as structured JSON and shares its schema with the server renderer. This is a suitable foundation for AI design tools. The current MCP endpoint exposes content, schemas and media; website design tools have not been added yet. See the [MCP website design assessment](../api/mcp#website-design-readiness) for the current capabilities and remaining integration work.

## Header, footer and menus

The header and footer are shown on every page and are edited in the same builder (**Header & footer** tab). Menus are managed in the **Menus** tab and can link to the homepage, list pages, entries or any URL, with up to five levels of nested items and per-language labels. Select an item in the menu tree to edit it. Drag items before, after or inside another item, or use the move and indent/outdent buttons. Group headings can contain children without linking anywhere. Deleting a parent asks you to confirm removal of the entire branch.

The destination picker separates pages, content entries, listing pages, custom links and sections. Content search is paginated and shows publication status; drafts and scheduled entries can be selected while preparing navigation, but linking them does not make them public. Only public content types with website routes (or homepage destinations) are offered.

For a section link, enter an anchor ID assigned in the page builder, such as `contact` or `#contact`. Choose **Section** to jump within the current page, or add an optional section to a page, entry or listing destination. Custom links also accept website paths, external URLs, `mailto:` and `tel:` links. Parent links and submenu disclosure buttons work independently on the published website.

## Theme

The **Theme** tab sets colors for light and dark mode (or follows the visitor's system), fonts and sizes, with a live preview. Fonts are local font stacks; nothing is loaded from third-party font services.

The theme also defines the **tokens** the builder offers, so one change reshapes the whole site:

- **Spacing scale** — the pixel values behind XS to 2XL for padding, space below and spacers (Compact / Normal / Relaxed fill them in).
- **Corners** — the S, M and L radii, and the default corner used by buttons, images, cards and “Theme default”. “Round” turns buttons into pills while images and cards use L.
- **Widths** — the maximum content width of “Contained” and “Narrow” sections.

Administrators can add **custom CSS**. Every class the website uses starts with `c-`, e.g. `.c-site-name` or `.c-button-primary`. Elements also accept extra CSS classes in the builder.

## Languages

Content types with locales can be published in several languages. Choose the website languages in **Overview**: the default language lives at the root (`/blog/hello`), others under their code (`/de/blog/hello`). Pages link their translations with `hreflang`.

## Search engines

Pages get a title (template in **Overview**, e.g. `{title} · {site}`), a description and a share image taken from the entry (or chosen per content type), a canonical URL, `/sitemap.xml` and `/robots.txt`. **Hide from search engines** adds `noindex` while a site is being built.

## Previewing drafts

The entry editor shows **Preview** when the website is on; it opens the last saved version of the entry, drafts included, for signed-in users only. People who manage the website also get a **Page design** section between **Core** and **Fields**, with an **Edit page** button. It opens the content type's shared page layout in the builder with that entry as the preview; layout changes apply to every entry of that type. In the builder, the top bar links to the live page and back to the entry's content. Published entries also offer **View on website** in the editor header.

## Domains and workspaces

The default workspace's website answers on the server's own address. To serve another workspace's website, add its domains in **Overview → Domains** and point those domains at the same installation.

## Performance and security

- Pages, 404 responses, the sitemap and robots.txt use the workspace's public API cache (`cache.enabled` and `cache.ttl`, 300 seconds by default). Content, schema, media and website configuration changes clear that workspace's cache. A scheduled publication shortens the cache lifetime so it becomes visible as soon as it is due.
- Cache entries are separate for each workspace, origin, installation prefix, path and page number. Unrelated query parameters do not create extra entries. Draft and builder previews bypass the public cache.
- Public pages set no cookies and support `ETag` revalidation; the stylesheet is versioned and cached for a year.
- Pages are sent with a `Content-Security-Policy` that forbids scripts. All content is escaped, and rich text and Markdown pass the same HTML allow-list as HTML fields.
- Layouts are data: every value is checked against a fixed vocabulary before it reaches the page.

Website settings are included in backups as the **Website** part.

## Dynamic content and smart tags

<div v-pre>

Ordinary elements can use manually written content, a field value, or text containing several field values. Click the database icon beside a supported property to choose its content source. Clicking an inserted chip edits its reference, formatting, and empty-value fallback. **Raw** switches to the source view, where you can edit content and smart tags directly; **Visual** switches back to the visual editor. Invalid tags must be corrected before saving. **Use static content** removes the binding.

Supported properties are heading text, Text content, image source/alt/caption, button label, section background image, and element link destinations. Layout and style controls stay static. The **Fields** sidebar contains shortcuts: Title inserts a bound Heading, scalar content inserts Text, and single-image media inserts an Image. Galleries, files, relation lists/cards, repeaters, badges, meters, swatches, and JSON retain specialized displays using the same reference system.

The picker offers these sources:

| Source | Smart tag example |
| --- | --- |
| Current entry | `{{ current.intro_text }}` |
| Current repeater item | `{{ item.heading }}` |
| Single page | `{{ single.company.phone }}` |
| Specific entry | `{{ entry.services["stable_uid"].description }}` |
| Site information | `{{ site.title }}`, `{{ site.tagline }}`, `{{ site.year }}` |

A single-entry relation can be followed, such as `{{ current.author.bio }}`. Entry sources expose `url`, including `{{ current.author.url }}`. Specific entries use stable UIDs so slug changes do not break references. Inside a repeater, Current item means its row and Current entry means the containing entry. In a card layout, Current entry is that card's entry. Current entry is unavailable in shared header/footer and list-page editors; use a singleton or a specific entry there.

Text can combine references with manual content, such as `Meet {{ current.author.title }}.` Images and link destinations require a complete reference of a compatible type. Multiple values need a specialized list/gallery/repeater display; a scalar element never silently selects the first value. Rich text and Markdown render their formatting when bound as the entire Text content. References embedded within other text become escaped plain text. Tags cannot be inserted into HTML attributes; use the dedicated image and link properties.

Formatting operations follow a pipe:

```text
{{ current.author.title | fallback:"Our team" }}
{{ current.published_at | date:"long" }}
{{ current.body | truncate:30 }}
```

`date` accepts `date`, `long`, `datetime`, or `time`. `truncate` limits words from 1 to 500. Fallbacks apply to missing or empty content; zero and false remain real values. Write `\{{` for literal opening braces and `\\` for a literal backslash in the syntax editor. Arithmetic, conditions, arbitrary functions, and recursive interpretation of field content are not supported.

References stay within the current workspace and use the active website language. Referenced content must be published and belong to a public content type, even when previewing a draft root entry. Private or missing media is not exposed. Relation paths follow at most five links. The builder marks unresolved references; published pages use the fallback or empty output.

The smart-reference layout format replaces the development format. Older saved layouts and header/footer designs show generated defaults with a notice until saved in the new format. Older layout revisions cannot be restored. Posts, schemas, media, website settings, menus, and theme are preserved.

</div>
