import { ssrRenderAttrs } from "vue/server-renderer";
import { useSSRContext } from "vue";
import { _ as _export_sfc } from "./plugin-vue_export-helper.1tPrXgE0.js";
const __pageData = JSON.parse('{"title":"OpenAPI","description":"","frontmatter":{},"headers":[],"relativePath":"api/openapi.md","filePath":"api/openapi.md"}');
const _sfc_main = { name: "api/openapi.md" };
function _sfc_ssrRender(_ctx, _push, _parent, _attrs, $props, $setup, $data, $options) {
  _push(`<div${ssrRenderAttrs(_attrs)}><h1 id="openapi" tabindex="-1">OpenAPI <a class="header-anchor" href="#openapi" aria-label="Permalink to &quot;OpenAPI&quot;">​</a></h1><p>The public API contract is available as an OpenAPI 3.1 document:</p><p><a href="/CometCMS/api/openapi.yaml">Download <code>openapi.yaml</code></a></p><p>Use it to generate clients, validate API responses, or import the API into tools such as Postman, Insomnia, Stoplight, or Swagger UI.</p></div>`);
}
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("api/openapi.md");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
const openapi = /* @__PURE__ */ _export_sfc(_sfc_main, [["ssrRender", _sfc_ssrRender]]);
export {
  __pageData,
  openapi as default
};
