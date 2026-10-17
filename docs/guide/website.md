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

**Website → Pages & URLs** lists every content type with its status, URLs and layouts. Every content type has up to three layouts:

- **Page** — a single entry.
- **Card** — how an entry looks inside lists, related-entry fields and entry lists elsewhere.
- **List page** — the page at the list URL.

Layouts are generated from the content type's fields until you change them, so a new type looks reasonable immediately.

The builder shows a live preview rendered by the same code as the real site. Layouts are built from **sections → rows → columns → elements**:

| Element      | Shows                                                                         |
| ------------ | ----------------------------------------------------------------------------- |
| Field        | A field of the entry, e.g. as text, formatted Markdown, image, gallery, cards. |
| Heading      | Fixed text; `{site}`, `{tagline}` and `{year}` are replaced.                   |
| Text         | Rich text.                                                                    |
| Image        | An image from the media library.                                              |
| Button       | A link to this entry, another entry, a list page, the homepage or any URL.    |
| Entry list   | Entries of any public collection, filtered, sorted and optionally paged.     |
| Menu         | One of the menus from the **Menus** tab.                                      |
| Site title   | Logo, title and tagline from the website settings.                            |
| Languages    | Links to the current page in the other website languages.                     |
| Spacer, Divider | Space and separators.                                                      |

Repeater fields contain an **item template**: the elements inside them are repeated for every row and bind to the repeater's sub-fields.

Drag elements from **Add** onto the preview or into the **Outline**, or click them to insert next to the selection. Select an element to change its content and style in the inspector. Style options come from the theme (sizes, colors, radius, shadows), which keeps every page consistent. Shortcuts: <kbd>Ctrl</kbd>+<kbd>Z</kbd> undo, <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>Z</kbd> redo, <kbd>Ctrl</kbd>+<kbd>D</kbd> duplicate, <kbd>Del</kbd> delete, <kbd>Ctrl</kbd>+<kbd>S</kbd> save.

If a field is renamed or removed, elements bound to it render nothing and the builder marks them.

## Header, footer and menus

The header and footer are shown on every page and are edited in the same builder (**Header & footer** tab). Menus are managed in the **Menus** tab and can link to the homepage, list pages, entries or any URL, with one level of sub-items and per-language labels.

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
