# Website

CometCMS provides a visual Page Builder with shared layouts and structured content fields. Pages are rendered by PHP on your server, so there is still nothing to install: no Node or build step. Pages use no JavaScript unless a gallery's modal viewer is enabled; that viewer uses a small bundled script. Developers can also enable the headless API per content type, independently of website pages.

The website is data-driven. You design a **layout per content type** once, and every entry of that type uses it. Editors keep filling in fields; the layout decides how those fields look.

## Putting it online

There is no separate on/off switch. A workspace's website is online while it has something to show: a **homepage**, or at least one content type with the **Page Builder enabled**. With neither, the workspace stays fully headless and the root URL shows a CometCMS placeholder with a quiet space scene.

Open **Page Builder** under **Website** in the admin sidebar (permission `site.manage`; shown once a content type has the Page Builder enabled or the website is online) to choose the homepage in the **Overview** tab, which also shows whether the website is online and which content types publish pages. Under **Content types → edit**, turn on **Enable Page Builder** in the base settings card. Published entries get their own publicly accessible page, with layouts designed in the Page Builder. The **Page Builder** card then appears with URL, search engine and layout settings:

| Setting       | Example         | Description                                                       |
| ------------- | --------------- | ----------------------------------------------------------------- |
| Entry URL     | `/blog/{slug}`  | Where each entry lives. Collections need exactly one `{slug}`.    |
| Page URL      | `/about`        | The fixed address of a single page.                               |
| List page URL | `/blog`         | Optional page listing all entries of a collection, with paging.   |

`/admin`, `/api`, `/mcp`, `/media`, `/media-thumbs`, `/_site` and `/site-preview` are reserved and cannot be used. **Private** content types are never published, and only `published` (or due `scheduled`) entries are visible — exactly like the public API.

The **Page Builder** column in the content types table shows which types have the Page Builder enabled. Users with `site.manage` can change this setting in the type’s base settings; other users see its status. The Page Builder cannot be enabled for private types. Enabling it for the first content type of a headless workspace puts its website online.

The homepage can be a single page, one entry, or a collection's list page, or **None**. Without a homepage, the site root shows a CometCMS placeholder with a starfield and subtle comet animation. Pages for content types with the Page Builder enabled remain accessible at their own URLs. Set the homepage to **None** and disable the Page Builder for all content types to return to a fully headless setup.

## Layouts and the builder

The **Content types** table shows which types have the Page Builder enabled. Open a content type’s **Page Builder** card to configure its URLs and edit its layouts. Every content type has up to two layouts:

- **Entry page** — a single entry.
- **List page** — the page at the list URL.

How entries look inside an entry list is designed in that list itself (see [Entry cards](#entry-cards)).

Layouts are generated from the content type's fields until you change them, so a new type looks reasonable immediately. The top bar marks such a page as **Default layout**; your first saved change creates your own version.

### Finding your way in the builder

- The **left rail** opens the panels: **Add**, **Outline**, **Content** (the preview entry's fields), **Components**, **Design**, **CSS** and **History**. Each panel explains what it does at the top.
- The **canvas** in the middle is the live preview. Click any element to select it; sections, grids, columns and components are outlined in their own colors.
- The **inspector** on the right shows the selected element's settings, a short explanation of the element type, and a path to its parent elements. With nothing selected, it summarizes the page you are editing and suggests next steps.
- The **top bar** shows the preview entry, language, device, undo/redo and whether all changes are saved.
- Press <kbd>?</kbd> or click the help icon to open the **builder guide** with the basics and all keyboard shortcuts. It opens automatically the first time you use the builder.

The builder shows a live preview rendered by the same code as the real site. Layouts are built from **sections → rows → columns → elements**. A column can also hold column sets of its own (up to three levels deep), for example a card with an image beside its text. Columns of such a nested set switch between stacked and side by side by the width of the column they sit in, not by the screen width:

| Element      | Shows                                                                         |
| ------------ | ----------------------------------------------------------------------------- |
| Field display | Specialized values such as downloads, repeaters, related cards, badges, or meters. |
| Heading      | Static text, field references, or a mixture of both.                           |
| Text         | Rich text.                                                                    |
| Image        | An image from the media library or a bound media field.                        |
| Gallery      | Selected media in a custom order, or media from a bound entry field, in a responsive grid with crop, spacing, and caption controls. |
| Button       | A link to this entry, another entry, a list page, the homepage or any URL.    |
| Accordion    | An expandable title and rich text content, optionally open on load. Duplicate it for more items or use it in a repeater template. |
| Icons        | A searchable selection of bundled Material Design icons, with size, color, an accessible label, and an optional link. Works without external icon requests. |
| Entry list   | Entries of any public collection, filtered, sorted and optionally paged.     |
| Menu         | One of the menus from the **Menus** tab.                                      |
| Site title   | Logo, title and tagline from the website settings.                            |
| Languages    | Links to the current page in the other website languages, as codes or names, with a flag beside each, or as flags alone. |
| Spacer, Divider | Space and separators.                                                      |

A column set (row) decides whether its columns sit side by side or underneath each other. Each column decides the same for its own elements under **Arrange elements**: underneath each other (default) or side by side, which wraps onto the next line when there is no room left. Side by side adds **Distribute elements** and **Align elements**, and **Space between elements** becomes the gap between them.

Repeater fields contain an **item template**: the elements inside them are repeated for every row and bind to the repeater's sub-fields.

Add a **Gallery** to any column or repeater item template. Choose media manually and drag the selected files to reorder them, or use the content reference picker to bind the gallery to a media field. Set one to six columns, spacing, aspect ratio, image fit and focal point, and optional captions from media titles. Galleries can also be included in reusable components. Existing **Field → Gallery** displays open as Gallery elements with their content reference and styling preserved.

Enable **Masonry layout** to stack images at their natural heights, flowing down each column before the next. The column count and spacing still apply, and captions stay with their images. Crop controls are hidden while masonry is enabled; turning it off restores the saved grid crop settings.

Enable **Open images in a modal** to show the clicked image at full size over a dark backdrop. Close it with the close button, a click on the backdrop, or Escape. **Show previous / next buttons** optionally enables navigation through that gallery, including the arrow keys. Captions follow the gallery's caption setting. The viewer works with grids and masonry; videos, audio, and downloads keep their normal controls. In the builder, select the gallery and click an image again to try the modal. Public pages allow only the exact bundled viewer script through a CSP hash; preview documents still block scripts and the trusted editor controls the viewer.

Drag elements from **Add** onto the preview or into the **Outline**, or click them to insert next to the selection. Select an element to change its content and style in the inspector. Style options come from the theme (sizes, colors, radius, shadows), which keeps every page consistent. Shortcuts: <kbd>Ctrl</kbd>+<kbd>Z</kbd> undo, <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>Z</kbd> redo, <kbd>Ctrl</kbd>+<kbd>D</kbd> duplicate, <kbd>Del</kbd> delete, <kbd>Ctrl</kbd>+<kbd>S</kbd> save.

Give elements descriptive names to find your way around larger layouts. In the **Outline**, double-click an element's label or click its pencil button to rename it. Press <kbd>Enter</kbd> or click away to apply, or <kbd>Esc</kbd> to cancel. Clear the name to restore its automatic label. Names are saved with the layout and support undo/redo; they also appear in the inspector and canvas selection without changing the website's content.

If a field is renamed or removed, elements bound to it render nothing and the builder marks them.

### Animations

Under **Style → Animation**, choose an effect and when it plays. **When scrolled into view** plays the entrance once as the element first appears in the viewport, using the theme's speed and stagger delay. **When the page opens** plays on load. **On Hover** starts from the element's current appearance, gently moves, scales, dims or blurs it, and returns when the pointer leaves. Reduced-motion preferences disable these effects.

Scroll inside the preview to test entry animations. Each preview update creates a new page and restores its scroll position, so elements already in view can play immediately. In browsers without [CSS scroll-triggered animations](https://developer.chrome.com/blog/scroll-triggered-animations), entrances progress with scrolling instead; an element already fully in view then looks finished when the effect is enabled. Browsers without view timelines play entrances on page load.

### Responsive column order

Select a **row** and enable **Reverse columns on mobile** and/or **Reverse columns on tablet** in the content inspector. Each setting applies independently: mobile is below 768 px, tablet is 768–1023 px, and desktop keeps the original order. All columns are reversed, including rows with more than two columns or columns that wrap onto multiple lines. This changes the visual order; keyboard and screen-reader order still follows the layout tree.

### Backgrounds and gradients

Sections, columns and elements have a **Background image** in the **Background** group of the **Style** tab, next to the background color. The image is layered over the color, which shows through transparent images and while the image loads. Once an image is chosen, the group also offers its size, focal point, repetition (for images that do not cover the area), an overlay that darkens or lightens it, and **Background blur (px)** from 0 to 40. Like other images, it can be bound to a media field of the entry.

Zero blur keeps the image sharp. The effect applies to a separate image layer, so headings, buttons, menus and overlays stay sharp. The image extends slightly behind the clipped edges to avoid a faded border when blurred.

Choose **Background gradient** in the same group for a two-color **Linear** or **Radial** gradient. Pick its start and end colors from the theme palette (including custom colors); linear gradients also have an angle from 0 to 360 degrees. The colors follow the site's light/dark mode. A gradient takes precedence over a background image; choose **None** to show the saved image again. The background color remains underneath as a fallback. Image blur, sizing and overlays apply to images only.

Headings and Text elements also offer **Text gradient** in the **Text** style group, with the same controls. It colors the text block, including linked and bound text, and works alongside a background color, gradient or image. Code blocks, highlighted text and table headers keep a solid text color for readability on their own backgrounds. **None** restores the regular text color. Browsers without text clipping support keep the regular text color.

Layouts saved before background images became a style keep their section images; they are moved into the section's style the next time the layout is loaded.

### Entry cards

An **Entry list** shown as cards repeats one card for each entry. At first that is the content type's **standard card**, generated from its fields. Choose **Customize card** in the inspector to give the list a card of its own: the first card in the preview becomes editable and every other entry follows it. A card is a column, so it can have a background, border, corners, shadow and padding, and it can hold elements and column sets. Inside a card, **Entry of this card** (`current`) is the entry the card shows, and the **Add** panel offers that content type's fields. **Use standard card** goes back to the standard card.

To use the same card in several lists, right-click it and choose **Save as component…**, then place that component in another list. Related-entry fields shown as cards use the standard card of the related type. Card layouts from earlier versions keep showing as the standard card of their type.

### Reusable components

A section, column set or column can be saved as a **component**, which reuses it, with everything inside it, on many pages. Right-click it on the preview or in the **Outline**, choose **Save as component…** and give it a name. That spot then shows the component; save the layout to keep it. Open the **Components** panel from the left rail to search all components, drag or click them into the page, edit them, or delete them.

A component is edited in one place. Choose **Edit component** in the inspector or the context menu, or open it from the **Components** tab of the website settings. Saving it updates every page that uses it. A placed component is one unit in the builder: its contents cannot be selected there. A column component placed in a column set keeps its own width. **Detach** replaces a placed component with an ordinary copy that you can change on its own, while the other places keep following the component.

Components may show fields of the entry a page displays (for example its title or author). Such a component belongs to the content type it was created in, and can only be added where an entry of that type is shown: that type's page layout and the cards of lists of that type. The Components panel lists it everywhere, with insertion disabled where it does not fit. Its builder previews a sample entry of that type. Components can contain other components, but never themselves.

Deleting a component turns all its instances into independent, editable copies, preserving their content, styling and column widths. This applies to layouts, headers, footers and instances inside other components, as well as the open builder draft. Older revisions also restore detached copies. The component and its own version history are permanently removed. Deletion is available in both the builder's **Components** panel and the **Components** tab of the website settings.

### Tabs

Add **Tabs** from **Add → Elements** to organize content into switchable panels. A new Tabs element starts with two tabs. In the inspector, rename tabs, change their order, add or remove tabs, and choose the **Default tab** shown when the page opens. **Title** provides the tab group's accessible name.

Choose **Edit content** or click a tab heading in the canvas to edit that panel. Each tab holds normal builder elements, including images, buttons, forms, collections and nested column layouts. Use the outline or the inspector's breadcrumb to return to the Tabs element; selecting an element inside a hidden tab reveals it automatically.

Visitors can switch tabs with a mouse or with Left/Right Arrow, Home and End on the tab headings, following the [WAI-ARIA Tabs pattern](https://www.w3.org/WAI/ARIA/apg/patterns/tabs/). Headings scroll horizontally when space is limited. Without JavaScript, all panels remain readable; printed pages include every panel.

### Forms

Forms let visitors send messages, sign-ups or requests. Every submission is saved as a new entry of a content type you choose, so the content type defines what the form asks for. For private inquiries, disable API exposure and the Page Builder on the receiving type.

Set forms up on the **content type edit page** by enabling **Allow Submissions**:

- The current content type receives the submissions and becomes available in the **Form** element's **Content type** selector. Single pages cannot receive submissions.
- The form shows **every field of that type** that visitors can fill in, in the type's order, so the form and the entries it creates always match. Labels, help texts, required flags, select options and widths (1/3, 1/2, 2/3) come from the content type's field builder; change them there. Text, textarea, Markdown, number, range, yes/no, select, date, date-time, colour and media fields are shown; relations, repeaters, HTML and JSON fields are left out, and a type where one of those is required cannot be used. Title and slug are filled in automatically.
- **Status of new entries** is *Draft* by default, so submissions wait for review.
- The button label and the messages shown after sending can be changed; empty texts use defaults in the page's language.

Disabling **Allow Submissions** removes the type from the Form element's selector and blocks submissions from existing placements; saved entries and form settings are retained.

Entries are titled with the form's name and the time they arrived. Submissions trigger the usual `content.created` webhook, which can be used to send notifications.

To place a form, click or drag **Form** from **Add → Elements** into the page, then select the **Content type** in the inspector. The form renders that type's fields and saves submissions as entries of the same type. The inspector also lets you hide labels or change the button's style. A text field whose key contains `email`, `phone` or `url` gets the matching input type.

Media fields accept file uploads and respect **Allow multiple** and **Required**. Attachments are stored privately in the receiving workspace’s media library and linked to the new entry; downloading them requires authenticated media access. Visitors cannot attach existing media by posting filenames.

The default limit is **10 MiB per file and 10 MiB total attachments per submission**. Change `site.forms.max_upload_bytes` in `cms/config/config.php` to set both limits in bytes. The effective limit cannot exceed `media.max_upload_bytes`; the form displays it beside each upload input. Allowed file types follow `media.allowed_mime_types`, with executable filename extensions rejected. PHP’s `upload_max_filesize`, `post_max_size`, `max_file_uploads`, and your web server’s request limits also apply; allow room for multipart overhead and text fields in the total request limit. Forms without media retain their 256 KiB request limit (`site.forms.max_body_bytes`); forms with media add the upload budget to that allowance.

Forms work without JavaScript. After sending, the visitor returns to the same page, where the form is replaced by the success message, or shows the error message above it. Spam protection uses a hidden trap field, and a limit of 5 submissions per visitor and form in 10 minutes (`site.forms.rate_limit_attempts` and `site.forms.rate_limit_window_seconds` in the configuration). Cloudflare Turnstile and Google reCAPTCHA are planned.

### AI and MCP access

The builder stores layouts as structured JSON and shares its schema with the server renderer. This is a suitable foundation for AI design tools. The current MCP endpoint exposes content, schemas and media; website design tools have not been added yet. See the [MCP website design assessment](../api/mcp#website-design-readiness) for the current capabilities and remaining integration work.

## Header, footer and menus

The header and footer are shown on every page and are edited directly in the page builder: select the header or footer to edit its shared layout. Menus are managed in the **Menus** tab and can link to the homepage, list pages, entries or any URL, with up to five levels of nested items and per-language labels. Select an item in the menu tree to edit it. Drag items before, after or inside another item, or use the move and indent/outdent buttons. Group headings can contain children without linking anywhere. Deleting a parent asks you to confirm removal of the entire branch.

Menu elements show a navigation list on desktop and collapse into a menu button on mobile by default. Enable **Always show menu button** in the element's content options to use the button at every screen size, including desktop. **Menu button opens** chooses what the button shows: a dropdown under the button, a full-screen menu with large centered links, or a side panel over a dimmed page. **Side panel position** chooses whether the panel slides in from the left or right (the default). For full-screen menus and side panels, **Menu panel background** and **Menu panel text color** select colors from the theme palette, including custom colors; **Default** keeps the theme's background and text colors. These colors apply to the open panel's content and close button. Full-screen menus and side panels have their own close button (clicking the dimmed page also closes a side panel), and the page behind them does not scroll while they are open.

### Sticky and floating headers

Each section has scroll options in its content settings, usually used for the section in the header that holds the logo and menu:

| Option                           | Effect                                                                                                                                                                                                                                               |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Stick to the top**             | **Always** keeps the section at the top of the window down the whole page. **When scrolling up** hides it while the visitor scrolls down and brings it back as soon as they scroll up. Other header sections, such as a top bar above, scroll away normally. |
| **Float over the content below** | The section takes no room, so the page's first section (a hero image, for example) starts underneath it. It stays transparent at the top of the page and shows its background once the visitor scrolls.                                             |
| **When scrolled**                | For sticky and floating sections: add a **Shadow**, make the background **Frosted** (translucent and blurred), or make the section **Compact** by reducing its vertical padding.                                                                      |

A floating sticky section is pinned to the very top of the window, so use it as the header's first section. When editing the header, the preview gets extra room so you can scroll it and try these options. These effects use CSS scroll states and require no JavaScript. Browsers without them, such as Firefox, keep **When scrolling up** sections always visible, show floating sections with their background, and skip the scroll effects.

The destination picker separates pages, content entries, listing pages, custom links and sections. Content search is paginated and shows publication status; drafts and scheduled entries can be selected while preparing navigation, but linking them does not make them public. Only public content types with website routes (or homepage destinations) are offered.

Element **Link** controls use the same destination picker, including Field and Gallery elements. Choose a page, entry, listing, custom URL, section, the current entry, or no link. The database icon binds the destination to a URL field or another compatible content reference, resolved for each entry or repeater item. Links can open in a new tab. Existing **Link to the entry** switches are preserved as **This entry** or **None** destinations. Gallery modal viewers take precedence over image links while enabled.

For a section link, enter an anchor ID assigned in the page builder, such as `contact` or `#contact`. Choose **Section** to jump within the current page, or add an optional section to a page, entry or listing destination. Custom links also accept website paths, external URLs, `mailto:` and `tel:` links. Parent links and submenu disclosure buttons work independently on the published website.

## Theme

The **Theme** tab sets colors for light and dark mode (or follows the visitor's system), fonts and sizes, with a live preview. Choose system font stacks or install the families you need. The website starter downloads Inter and Lora as normal installed fonts; you can replace or remove them once they are no longer used. Empty workspaces start without installed fonts. **Manage fonts** opens the installed library, the online catalogue and custom uploads; the same library is also available as the **Fonts** tab on the Page Builder settings page. Every family is shown as a live specimen, and you can type your own preview text and change its size. Website and editor font files are always served from your CometCMS installation.

The **Catalogue** lets website managers search and filter Fontsource families by category, see each family's preview as it scrolls into view, and install selected character sets. Latin and extended Latin are selected by default where available. Installations show progress and can be cancelled or resumed after an interruption. Catalogue access requires outbound HTTPS from the PHP host; installed fonts continue working offline. Updates happen only when you choose **Update font**.

**Upload** accepts custom WOFF2 files, with a family name, fallback category, style and weight for each file. Drop files onto the upload area; style and weight are suggested from the file names and each file shows a local preview before upload. Enter identical start and end weights for a static face, or the supported range for a variable face. Upload italic files separately and confirm permission to use the fonts. You can add more files to an existing custom family. Files are limited to 10 MiB each and 100 MiB per family, subject to your host's upload limits.

Installed fonts are available for body text, headings, H1–H6 overrides and individual builder elements. System stacks and the Body/Heading element options remain available. Only faces used by the page are downloaded by the browser. Missing files use fallback fonts and are marked in the editor.

Site backups include installed fonts and their metadata. Fonts referenced by saved designs or the current draft cannot be removed; the library shows their usages. Bundled fonts remain available with every installation.

**Heading levels** fine-tune H1 to H6 one by one: font, size, weight, line height, letter spacing and capitalization. Empty values follow the general typography settings, so a level only needs the values that should differ. Custom sizes from 32 px shrink by 20 % on phones. Font, size and weight chosen for a single element in the builder still take precedence.

The theme also defines the **tokens** the builder offers, so one change reshapes the whole site:

- **Spacing scale** — the pixel values behind XS to 2XL for padding, space below and spacers (Compact / Normal / Relaxed fill them in).
- **Corners** — the S, M and L radii, and the default corner used by buttons, images, cards and “Theme default”. “Round” turns buttons into pills while images and cards use L.
- **Widths** — the maximum content width of “Contained” and “Narrow” sections.

Administrators can add **custom CSS**. Every class the website uses starts with `c-`, e.g. `.c-site-name` or `.c-button-primary`. Elements also accept extra CSS classes in the builder.

### Theme folder

Each workspace keeps the whole design of its website in one folder, `storage/workspaces/{workspace}/site/theme/`, so it can be versioned with git or copied on its own:

```
theme/
  theme.json                    design settings
  style.css                     custom CSS
  regions/header.json           header and footer
  regions/footer.json
  layouts/{type}/detail.json    page layout of a content type
  layouts/{type}/index.json     its list page
  components/{name}.json        reusable components
```

Public pages load `style.css` after the generated theme styles. The builder previews unsaved CSS immediately, and theme revisions include both settings and CSS. You can also edit `style.css` directly; stylesheet versions and page caches account for its contents.

Components are stored under their name, e.g. `components/hero-banner.json`, and layouts refer to them by that name. Renaming a component in the builder keeps its file name, so placements stay intact. The files contain no save times: saving without changes leaves them untouched, and the time shown as "last changed" is the file's modification time. Revisions are kept outside the folder, in `storage/workspaces/{workspace}/revisions/site/`. Settings, menus and forms belong to the site rather than its design and stay in `site/`. Website backups include all of it.

Workspaces from earlier versions move their headers, footers, layouts and components into the theme folder on the first request after updating. Components with generated names such as `ce1f4a17586` get readable names, and every placement, also in revisions, is updated. Backups made before this change can still be restored. Existing `site/theme.json` files remain readable and move into the folder the next time the theme is saved.

## Languages

Configure languages in **Workspaces → Edit workspace**, then choose the website's enabled subset in **Overview**. The default language lives at the root; other languages use their code as a prefix. Slugs and route paths can be translated, for example `/articles/about` and `/de/artikel/ueber-uns`. Internal links use stable entry IDs, so changing a slug does not break them.

The builder keeps one shared structure and design. Choose a language to edit its headings, rich text, button labels, images, links, tabs, and other visitor-facing values. **Translate content** prevents structural edits; **Shared design** enables shared layout changes. Source text, inheritance, reset actions, and review indicators appear in the inspector. Component translations apply wherever the component is used; header and footer translations work the same way. A literal in a collection layout applies to every entry using it; bind a custom field when the value should differ per entry.

Site identity, metadata, error pages, navigation, form text, field presentation labels, and media alternative text and captions use the same localized-value model. Language selectors in these editors share the active language. Built-in visitor messages use a separate language dictionary.

Publish entry translations independently. Missing values within a published translation inherit public default-language content; an empty override stays empty. Missing or unpublished page variants return 404. Sitemaps, canonical URLs, `hreflang`, and the language switcher use available published variants. Shared design, components, and settings keep their existing save lifecycle.

The **Languages** element links each page to its published translations and omits unavailable languages. **Flags** shows the text only, a flag beside the text, or the flag alone; a flag on its own is named for screen readers and shows the language name on hover. Flags stand for languages, so they follow a convention: English shows the British flag and regional codes use their region (`de-at` shows Austria's flag, `pt-br` Brazil's). A language without a flag keeps its text.

## Search engines

Pages get a title (template in **Overview**, e.g. `{title} · {site}`), a description and a share image taken from the entry (or chosen per content type), a canonical URL, `/sitemap.xml` and `/robots.txt`. **Hide from search engines** adds `noindex` while a site is being built.

## Previewing drafts

People who manage the website get a **Page design** section between **Core** and **Content fields** in the entry editor, with an **Edit page** button. It opens the content type's shared page layout in the builder with that entry as the preview; layout changes apply to every entry of that type. In the builder, the save button's menu offers **Preview**, which opens the last saved layout with the sample entry, drafts included, for signed-in users only and marked with a preview banner. Save first to include unsaved layout changes. The same menu has **View live page** for the published page, and the top bar links back to the entry's content. Published entries also offer **View on website** in the editor header.

## Domains and workspaces

The default workspace's website answers on the server's own address. To serve another workspace's website, add its domains in **Overview → Domains** and point those domains at the same installation.

## Performance and security

- Pages, 404 responses, the sitemap and robots.txt use the workspace's public API cache (`cache.enabled` and `cache.ttl`, 300 seconds by default). Content, schema, media and website configuration changes clear that workspace's cache. A scheduled publication shortens the cache lifetime so it becomes visible as soon as it is due.
- Cache entries are separate for each workspace, origin, installation prefix, path and page number. Unrelated query parameters do not create extra entries. Draft and builder previews bypass the public cache.
- Public pages set no cookies and support `ETag` revalidation; the stylesheet is versioned and cached for a year.
- Pages are sent with a `Content-Security-Policy` that blocks scripts except for the exact bundled gallery and Tabs modules when needed, allowed by their content hashes. All content is escaped, and rich text and Markdown pass the same HTML allow-list as HTML fields.
- Layouts are data: every value is checked against a fixed vocabulary before it reaches the page.

Website settings are included in backups as the **Website** part.

## Dynamic content and smart tags

<div v-pre>

Ordinary elements can use manually written content, a field value, or text containing several field values. Click the database icon beside a supported property to choose its content source. Clicking an inserted chip edits its reference, formatting, and empty-value fallback. **Raw** switches to the source view, where you can edit content and smart tags directly; **Visual** switches back to the visual editor. Invalid tags must be corrected before saving. **Use static content** removes the binding.

Supported properties are heading text, Text content, image and gallery sources, button label, background images, and element link destinations. Image alt text and captions come from the selected media item's alt text and title. Other layout and style controls stay static. The **Content fields** sidebar contains shortcuts: Title inserts a bound Heading, scalar content inserts Text, single-image media inserts an Image, and multiple-media fields insert a Gallery. Files, relation lists/cards, repeaters, badges, meters, swatches, and JSON retain specialized displays using the same reference system.

The picker offers these sources:

| Source | Smart tag example |
| --- | --- |
| Current entry | `{{ current.intro_text }}` |
| Current repeater item | `{{ item.heading }}` |
| Single page | `{{ single.company.phone }}` |
| Specific entry | `{{ entry.services["stable_uid"].description }}` |
| Site information | `{{ site.title }}`, `{{ site.tagline }}`, `{{ site.year }}` |

A single-entry relation can be followed, such as `{{ current.author.bio }}`. Entry sources expose `url`, including `{{ current.author.url }}`. Specific entries use stable UIDs so slug changes do not break references. Inside a repeater, Current item means its row and Current entry means the containing entry. Inside an entry list's card, Current entry is that card's entry. Current entry is unavailable in shared header/footer and list-page editors; use a singleton or a specific entry there.

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
