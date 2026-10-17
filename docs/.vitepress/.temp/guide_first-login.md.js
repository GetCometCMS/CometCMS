import { ssrRenderAttrs, ssrRenderAttr } from "vue/server-renderer";
import { useSSRContext } from "vue";
import { _ as _export_sfc } from "./plugin-vue_export-helper.1tPrXgE0.js";
const _imports_0 = "/CometCMS/assets/first-login.Cbj1ao2-.png";
const __pageData = JSON.parse('{"title":"First Login","description":"","frontmatter":{},"headers":[],"relativePath":"guide/first-login.md","filePath":"guide/first-login.md"}');
const _sfc_main = { name: "guide/first-login.md" };
function _sfc_ssrRender(_ctx, _push, _parent, _attrs, $props, $setup, $data, $options) {
  _push(`<div${ssrRenderAttrs(_attrs)}><h1 id="first-login" tabindex="-1">First Login <a class="header-anchor" href="#first-login" aria-label="Permalink to &quot;First Login&quot;">​</a></h1><h2 id="setup-screen" tabindex="-1">Setup screen <a class="header-anchor" href="#setup-screen" aria-label="Permalink to &quot;Setup screen&quot;">​</a></h2><p>When you visit <code>/admin</code> for the first time (or after a <a href="./recovery.html">user reset</a>), CometCMS shows the <strong>setup screen</strong> instead of the login page.</p><p>Enter a username and password (minimum 8 characters), then choose the first workspace name (optional custom slug).</p><p>Submitting setup creates:</p><ul><li>The initial admin account.</li><li>The initial workspace.</li><li>The default workspace assignment (used as the admin workspace fallback when <code>X-Comet-Workspace</code> is not set).</li></ul><p>This admin account is the only account that can access the panel until more users are added.</p><p><img${ssrRenderAttr("src", _imports_0)} alt="CometCMS setup screen for creating the first admin account"></p><h2 id="admin-url" tabindex="-1">Admin URL <a class="header-anchor" href="#admin-url" aria-label="Permalink to &quot;Admin URL&quot;">​</a></h2><p>The admin panel is always available at:</p><div class="language- vp-adaptive-theme"><button title="Copy Code" class="copy"></button><span class="lang"></span><pre class="shiki shiki-themes github-light github-dark vp-code" tabindex="0"><code><span class="line"><span>https://yourdomain.com/admin</span></span></code></pre></div><h2 id="logging-in" tabindex="-1">Logging in <a class="header-anchor" href="#logging-in" aria-label="Permalink to &quot;Logging in&quot;">​</a></h2><p>Enter your username and password. Sessions are server-side (PHP sessions stored in <code>cms/storage/sessions/</code>).</p><p>After login, you can add more workspaces in <strong>Settings -&gt; Workspaces</strong>. See <a href="./workspaces.html">Workspaces</a> for routing, isolation, and permission scoping details.</p><h2 id="your-profile" tabindex="-1">Your profile <a class="header-anchor" href="#your-profile" aria-label="Permalink to &quot;Your profile&quot;">​</a></h2><p>Click your <strong>name or avatar in the bottom-left corner of the sidebar</strong> to open your profile page where you can:</p><ul><li>Upload or remove your profile picture</li><li>Change your display name and email address</li><li>Set a new password (requires entering your current password first)</li></ul></div>`);
}
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("guide/first-login.md");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const firstLogin = /* @__PURE__ */ _export_sfc(_sfc_main, [["ssrRender", _sfc_ssrRender]]);
export {
  __pageData,
  firstLogin as default
};
