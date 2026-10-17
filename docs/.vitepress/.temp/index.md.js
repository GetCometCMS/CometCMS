import { ssrRenderAttrs } from "vue/server-renderer";
import { useSSRContext } from "vue";
import { _ as _export_sfc } from "./plugin-vue_export-helper.1tPrXgE0.js";
const __pageData = JSON.parse(`{"title":"","description":"","frontmatter":{"layout":"home","hero":{"name":"CometCMS","text":"The CMS that fits on any PHP host","tagline":"File-based, headless, zero-dependency. Upload a ZIP, open /admin — you're done.","image":{"light":"/cms-logo-black.png","dark":"/cms-logo-white.png","alt":"CometCMS"},"actions":[{"theme":"brand","text":"Get Started","link":"/guide/introduction"},{"theme":"alt","text":"Documentation","link":"/guide/installation"},{"theme":"alt","text":"Download","link":"https://github.com/GetCometCMS/CometCMS/releases/latest"}]},"features":[{"icon":"📁","title":"File-based storage","details":"Content is stored as plain JSON files. No database to set up, migrate, or maintain.","link":"/guide/content-types","linkText":"Content types"},{"icon":"🔌","title":"Headless & API-first","details":"A clean public REST API lets you consume content from any frontend — Next.js, Astro, SvelteKit, or plain fetch.","link":"/api/public-api","linkText":"API reference"},{"icon":"🧩","title":"Flexible field types","details":"Build schemas with text, rich text, media, relations, selects, dates, and more.","link":"/guide/field-types","linkText":"Field types"},{"icon":"🚀","title":"Zero server dependencies","details":"PHP 8.1+ is the only production requirement. No Composer, no database, no CLI, no SSH, no Node.js on the server.","link":"/guide/installation","linkText":"Installation"},{"icon":"🔑","title":"Scoped access tokens","details":"Fine-grained permissions per action, content type, media category, and field. No overly broad keys.","link":"/guide/api-tokens","linkText":"Access tokens"},{"icon":"💾","title":"Built-in backups","details":"Create and restore full backups of all content, media, and settings directly from the admin UI.","link":"/guide/backups","linkText":"Backup & restore"}]},"headers":[],"relativePath":"index.md","filePath":"index.md"}`);
const _sfc_main = { name: "index.md" };
function _sfc_ssrRender(_ctx, _push, _parent, _attrs, $props, $setup, $data, $options) {
  _push(`<div${ssrRenderAttrs(_attrs)}></div>`);
}
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("index.md");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const index = /* @__PURE__ */ _export_sfc(_sfc_main, [["ssrRender", _sfc_ssrRender]]);
export {
  __pageData,
  index as default
};
