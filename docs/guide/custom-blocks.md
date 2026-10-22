# Custom blocks

<div v-pre>

**Block packages** add your own elements to the page builder. A package is a ZIP of plain files: a manifest, one folder per block with an HTML template and CSS, and optional assets. Packages contain no code. CometCMS validates and compiles them at installation, and every block then renders on the server, identically in the builder preview and on the published website.

Blocks behave like built-in elements. They appear in the elements palette, get an inspector generated from their props, accept the standard element styles, and work in columns, tabs, reusable components, collection cards and header/footer layouts. Their text props are translatable like built-in text.

## Quick start

The repository includes a complete example in `examples/blocks/acme-marketing/`:

```text
acme-marketing/
  comet-package.json        # required: name, version, title
  style.css                 # optional: CSS shared by all blocks of the package
  README.md                 # optional: README.md, LICENSE(.md/.txt), CHANGELOG.md are kept but ignored
  assets/
    check.svg               # optional: images, fonts and videos
    chevron.svg
  blocks/
    callout/
      block.json            # required: title, description, props
      template.html         # required: the markup
      style.css             # optional
    faq/…
    feature/…
```

1. Check the package: `npm run blocks -- validate examples/blocks/acme-marketing`
2. Pack it: `npm run blocks -- pack examples/blocks/acme-marketing` (writes `acme-marketing-1.0.0.zip`)
3. As an administrator, open **Page Builder → Custom blocks** and drop the ZIP on the upload area.
4. Open a layout. **Callout**, **FAQ item** and **Feature** are in the elements palette.

Zipping the folder yourself works too. A single top-level folder inside the ZIP is unwrapped, and `.DS_Store`, `__MACOSX/` and other dotfiles are ignored. The CLI runs the same validation as the installer, without a running CMS. It needs only PHP with the zip extension.

## comet-package.json

```json
{
  "api_version": 1,
  "name": "acme/marketing",
  "title": "Acme marketing blocks",
  "description": "A callout, an FAQ item and a feature card that work with any theme.",
  "version": "1.0.0",
  "author": "Acme",
  "license": "MIT"
}
```

| Field | Rules |
| --- | --- |
| `api_version` | Required. Always `1`. |
| `name` | Required. `vendor/package`, each part starting with a lowercase letter, up to 40 lowercase letters, digits or hyphens. The vendors `comet` and `core` are reserved. Uploading a package with an installed name updates it. |
| `title` | Required. Up to 120 bytes. |
| `version` | Required. Semantic version such as `1.0.0` or `1.1.0-beta.1`. |
| `description`, `author`, `license`, `homepage` | Optional text. `homepage` is an http(s) URL. |

Other fields are rejected, so a typo cannot go unnoticed.

## Blocks and block.json

Every folder `blocks/<name>/` is one block. Its name combines the package vendor and the folder name: `blocks/callout` in `acme/marketing` is the block **`acme/callout`**, stored in layouts as the kind `block:acme/callout`. Block folder names follow the same rules as name parts. A block name belongs to one package; installing another package that defines it is rejected.

```json
{
  "title": "Callout",
  "description": "An announcement with an optional link.",
  "props": {
    "title": { "type": "string", "label": "Heading", "max": 200, "default": "Something worth sharing" },
    "tone": { "type": "enum", "label": "Tone", "options": ["info", "success"], "default": "info" },
    "show_link": { "type": "bool", "label": "Show link", "default": true },
    "url": { "type": "url", "label": "Link URL", "default": "#details" }
  }
}
```

`title` (up to 120 bytes) is the palette label, and the optional `description` (up to 500 bytes) its hint. Do not add `name`; the folder decides it. A block has at most 32 props. Prop names start with a lowercase letter and use lowercase letters, digits and underscores.

| Type | Optional settings | Default if omitted | Editor and saved value |
| --- | --- | --- | --- |
| `string` | `max` 1–5000 (default 500) | `""` | Single-line text, trimmed and length-limited. Translatable. |
| `url` | `max` 1–5000 (default 2000) | `""` | Relative, `http(s)`, `mailto` or `tel` URL; anything else falls back to the default. Translatable. |
| `bool` | none | `false` | Checkbox. |
| `int` | `min`, `max` within ±1,000,000 | 0, clamped to the range | Number, clamped to the range. |
| `enum` | `options` (required): 1–50 values of letters, digits, `-` and `_` | the first option | Select. Options double as CSS class fragments. |

Every prop needs `type` and `label` (up to 120 bytes). Values are validated when layouts are saved, previewed and rendered. Values of props a package no longer declares are dropped the next time the layout is saved.

## template.html

Templates are HTML with Mustache tags. All values are HTML-escaped.

| Tag | Meaning |
| --- | --- |
| `{{title}}` | The prop's value, escaped. |
| `{{#show_link}}…{{/show_link}}` | Rendered when the prop is truthy. Empty strings, `false`, `0` and `"0"` are falsy. |
| `{{^show_link}}…{{/show_link}}` | Rendered when the prop is falsy. |
| `{{#tone=success}}…{{/tone}}` | Rendered when an enum prop has this option. `{{^tone=success}}` is the opposite. The closing tag may repeat the option. |
| `<details {{#open}}open{{/open}}>` | Inside a start tag, a section toggles one boolean attribute: `open`, `hidden`, `inert`, `disabled`, `controls`, `autoplay`, `muted`, `loop`, `playsinline` or `reversed`. |
| `{{! comment }}` | Removed. |

Elsewhere inside a start tag, tags may only appear in quoted attribute values: `class="acme-callout acme-callout--{{tone}}"`. Sections can nest 16 deep. Each section must open and close inside the same element, or inside the same attribute value, so every combination of values produces well-formed HTML. There is no raw output (`{{{x}}}`, `{{& x}}`), no partials, no dotted names and no delimiter changes. In this version, `{{#prop}}` never loops.

The callout template uses most of this:

```html
{{! The wrapper element gets the class c-block-acme__callout automatically. }}
<aside class="acme-callout acme-callout--{{tone}}" role="note">
  {{#tone=success}}<img class="acme-callout__icon" src="assets/check.svg" alt="" width="24" height="24">{{/tone=success}}
  <div>
    <h3 class="acme-callout__title">{{title}}</h3>
    <p>{{message}}</p>
    {{#show_link}}{{#url}}<a class="acme-callout__link" href="{{url}}">{{link_label}}</a>{{/url}}{{/show_link}}
  </div>
</aside>
```

### Allowed HTML

Templates are parsed at installation and rejected, with file and line, when they contain anything outside this list. Nothing is silently removed.

- **Elements:** `a abbr address article aside b bdi blockquote br button caption cite code col colgroup dd del details dfn div dl dt em figcaption figure footer h1–h6 header hgroup hr i img ins kbd li mark nav ol p picture pre q s samp search section small source span strong sub summary sup table tbody td tfoot th thead time tr u ul var video wbr`
- **Inline SVG:** `svg g defs path circle ellipse rect line polyline polygon linearGradient radialGradient stop`, with geometry and presentation attributes such as `viewBox`, `d`, `fill`, `stroke`, `stroke-width` and `transform`.
- **On every element:** `class id title role lang dir hidden inert popover translate tabindex`, plus any `aria-*` and `data-*` attribute.
- **Element attributes:** for example `a[href target rel hreflang]`, `img[src srcset sizes alt width height loading decoding fetchpriority]`, `video[src poster controls autoplay muted loop playsinline preload]`, `details[open name]`, `button[type popovertarget popovertargetaction disabled]`, `td/th[colspan rowspan headers]`, `time[datetime]`.

Not allowed: `script`, `style`, `link`, `iframe`, forms and form controls, inline `style` attributes and `on*` event handlers. Buttons must be `type="button"`. A `<p>` cannot contain block elements such as `<section>` or `<div>`, because browsers would close the paragraph early. Links with a `target` automatically get `rel="noopener noreferrer"`.

**URLs.** `href`, `src` and `poster` take a literal URL, an `assets/…` path, or exactly one `url`-type prop: `href="{{url}}"`. A `string` prop cannot be a URL, and URLs cannot be assembled from several parts. Literal URLs must be absolute (`https://…`), start with `/`, `#` or `?`, or use `mailto:` or `tel:`. A bare relative path like `logo.png` is rejected, because it would resolve against each page's address. `srcset` accepts literal URLs and `assets/…` paths only.

**Markup is normalized.** The stored template is the parsed HTML written back out, so `<li>` without closing tags, self-closing SVG elements and similar shorthand are fine.

### Interactivity without JavaScript

Packages cannot run JavaScript, but modern HTML and CSS cover many interactive patterns:

- **Accordions:** `<details>`/`<summary>`. Items with the same `name` close each other (see the `faq` block).
- **Menus, popovers and tooltips:** `<button type="button" popovertarget="x">` with `<div id="x" popover>`.
- **Carousels:** CSS scroll snapping on a horizontally scrolling list.
- **State-dependent styling:** `:has()`, `:target`, `details[open]`, and scroll-driven animations.

Use a prop for `id` values, or include one in the template, when a block can appear several times on a page and its elements reference each other.

## CSS and assets

Each placed block is wrapped in an element with the class `c-block-<vendor>__<block>`, for example `.c-block-acme__callout`, alongside the standard element classes. Prefix your selectors with it. Use theme variables with fallbacks, so the block adapts to any theme:

```css
.c-block-acme__callout .acme-callout {
  padding: var(--space-lg, 1.5rem);
  border-inline-start: 4px solid var(--c-primary, #2563eb);
  color: var(--c-text, #0f172a);
}
```

A page includes the CSS of the packages and blocks it renders, once each, in a `<style data-comet-blocks>` element after the theme. The package-level `style.css` comes first; use it for shared custom properties or `@font-face`. CSS cannot contain `<`; write `\3C` inside strings.

Files under `assets/` (in up to three levels of subfolders) can be `svg png jpg jpeg gif webp avif woff2 mp4 webm`, at most 8 MiB each. Their content must match the extension. Reference them as `assets/…` from templates and as `url(assets/…)` from any CSS file in the package. CometCMS rewrites these to content-hashed URLs under `/_site/<workspace>/blocks/`, served with long-lived immutable caching. Other relative URLs in CSS are rejected, for the same reason as in templates.

## Install, update and delete

| Action | How | Effect |
| --- | --- | --- |
| Install | Upload a ZIP in **Page Builder → Custom blocks**, or copy the package folder to `cms/storage/workspaces/<workspace>/site/blocks/<vendor>/<package>/` (`storage/…` in a production build) | The package is validated completely before anything is written. A rejected upload changes nothing. |
| Update | Upload a package with the same name | Placed blocks change immediately. If the update removes a block, removes a prop, changes a prop's type or drops an enum option, the upload lists these changes and asks for confirmation first. |
| Delete | **Delete** beside the package, or remove its folder | Placed blocks keep their settings in the layouts. The builder shows them as missing blocks, and the website omits them until the package is installed again. |

Packages belong to one workspace and are independent of its theme. Workspace backups include them. A workspace holds up to 64 packages and 256 blocks; a package holds up to 64 blocks, 500 files and 25 MiB.

Copied folders take effect on the next request; there is no registration step. CometCMS keeps a compiled index of all packages and rebuilds it whenever a package file's size, modification time or inode changes. Packages in the folder that fail validation are listed with their errors in **Custom blocks**, without affecting the other packages. The folder name must match the package `name`. Reload an open builder after installing or updating a package.

Keep your packages in version control. Versions are informational: there is no dependency resolution and no rollback beyond reinstalling an older ZIP.

## Errors

Validation reports every problem at once, as `file:line: message`, in the upload panel, the API response and the CLI:

```text
blocks/faq/template.html:3: {{{answer}}}: Raw output with {{{name}}} or {{& name}} is not supported; values are always escaped. Use {{name}}.
blocks/feature/style.css:1: The relative URL "images/bg.png" would resolve against each page's address. Reference package files as url(assets/…).
```

For coding agents, the loop is: edit the package, run `npm run blocks -- validate <folder>`, and fix what it reports.

## Trust and permissions

Installing, updating and deleting packages requires an administrator with the workspace's `site.manage` permission, and a valid CSRF token. Editors who can edit layouts can place installed blocks.

| Capability | Policy |
| --- | --- |
| Data | Blocks see only their own validated props. No content queries, media library, sessions or environment. |
| Code | None. No JavaScript, PHP, build steps, hooks or package managers. JavaScript files are rejected. |
| Markup | Allow-listed at installation; values are always escaped. |
| CSS | **Trusted, like custom theme CSS.** It is not sandboxed or scoped, so it can restyle the whole website and load external fonts and images. Prefixing selectors is a convention, not a security boundary. Only install packages from authors you trust. |
| Assets | Served with `Content-Security-Policy: sandbox` and `nosniff`, so an SVG opened directly cannot run scripts. |

The builder preview keeps its script prohibition, and package CSS never enters the admin interface itself.

## API

| Request | Purpose |
| --- | --- |
| `GET /admin/api/site/blocks` | Installed packages with their blocks, folder packages that failed to load (`errors`), and `can_install`. Requires `site.manage`. |
| `POST /admin/api/site/blocks` | Multipart upload with the ZIP in `package`. Returns 201 with `name`, `version`, `previous_version`, `blocks` and `changes`. Invalid packages return 422 `invalid_block_package` with `details`: a list of `{file, line, message}`. Breaking updates return 409 `block_package_changes` with `details.changes`; send `accept_changes=1` to proceed. |
| `DELETE /admin/api/site/blocks/{vendor}/{package}` | Deletes the package. Returns 404 `not_found` when it is not installed. |

The workspace header and CSRF handling are those of the other admin endpoints.

## Not yet supported

These are deliberately left out of version 1 and planned as additive extensions of the same package format:

- **Repeating content:** list props with item schemas (iterated by `{{#items}}…{{/items}}`) and child slots, for FAQ lists, pricing tables or card grids built from one block.
- **More prop types:** multi-line and rich text, media from the library, colours from the theme, and bindings to collection fields.
- **JavaScript:** an optional per-block script with its own permission and Content-Security-Policy integration, running only on the published website.

</div>
