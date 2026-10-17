import { ssrRenderAttrs } from "vue/server-renderer";
import { useSSRContext } from "vue";
import { _ as _export_sfc } from "./plugin-vue_export-helper.1tPrXgE0.js";
const __pageData = JSON.parse('{"title":"Introduction","description":"","frontmatter":{},"headers":[],"relativePath":"guide/introduction.md","filePath":"guide/introduction.md"}');
const _sfc_main = { name: "guide/introduction.md" };
function _sfc_ssrRender(_ctx, _push, _parent, _attrs, $props, $setup, $data, $options) {
  _push(`<div${ssrRenderAttrs(_attrs)}><h1 id="introduction" tabindex="-1">Introduction <a class="header-anchor" href="#introduction" aria-label="Permalink to &quot;Introduction&quot;">​</a></h1><p>CometCMS is a lightweight, <strong>file-based headless CMS</strong> built with PHP and a Vue 3 admin interface. It requires no database — all content, users, and settings are stored as plain JSON files on disk.</p><h2 id="key-concepts" tabindex="-1">Key concepts <a class="header-anchor" href="#key-concepts" aria-label="Permalink to &quot;Key concepts&quot;">​</a></h2><table tabindex="0"><thead><tr><th>Concept</th><th>Description</th></tr></thead><tbody><tr><td><strong>Content type</strong></td><td>A schema that defines either a collection of entries (e.g. <code>posts</code>) or one single page-like entry (e.g. <code>start-page</code>).</td></tr><tr><td><strong>Content entry</strong></td><td>A saved item made up of the fields defined by its content type.</td></tr><tr><td><strong>Media</strong></td><td>Uploaded images/files, managed through the media library.</td></tr><tr><td><strong>User</strong></td><td>A person who can log in to the admin panel. Users receive permissions through their assigned role.</td></tr><tr><td><strong>Access token</strong></td><td>A bearer token that grants REST API and MCP access with specific permission grants.</td></tr></tbody></table><h2 id="roles" tabindex="-1">Roles <a class="header-anchor" href="#roles" aria-label="Permalink to &quot;Roles&quot;">​</a></h2><table tabindex="0"><thead><tr><th>Role</th><th>Permissions</th></tr></thead><tbody><tr><td><code>admin</code></td><td>Built-in full-access role. It cannot be deleted.</td></tr><tr><td><code>editor</code></td><td>Built-in content and media editing role.</td></tr><tr><td><code>viewer</code></td><td>Built-in read-oriented role.</td></tr></tbody></table><p>Roles can be customized or created from <strong>Users → Edit user roles</strong>.</p><h2 id="storage-layout" tabindex="-1">Storage layout <a class="header-anchor" href="#storage-layout" aria-label="Permalink to &quot;Storage layout&quot;">​</a></h2><div class="language- vp-adaptive-theme"><button title="Copy Code" class="copy"></button><span class="lang"></span><pre class="shiki shiki-themes github-light github-dark vp-code" tabindex="0"><code><span class="line"><span>cms/storage/</span></span>
<span class="line"><span>  content/          # Content entries (one JSON file per entry)</span></span>
<span class="line"><span>  content-types/    # Content type schemas</span></span>
<span class="line"><span>  media/            # Uploaded media files</span></span>
<span class="line"><span>  media-meta/       # Media metadata</span></span>
<span class="line"><span>  revisions/        # Entry revision history</span></span>
<span class="line"><span>  sessions/         # PHP session files</span></span>
<span class="line"><span>  trash/            # Soft-deleted entries</span></span>
<span class="line"><span>  users/            # User accounts (one JSON file per user)</span></span>
<span class="line"><span>  roles/            # User role definitions and permission grants</span></span>
<span class="line"><span>  logs/             # Application logs</span></span>
<span class="line"><span>  backups/          # Saved backup ZIP files</span></span></code></pre></div></div>`);
}
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("guide/introduction.md");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const introduction = /* @__PURE__ */ _export_sfc(_sfc_main, [["ssrRender", _sfc_ssrRender]]);
export {
  __pageData,
  introduction as default
};
