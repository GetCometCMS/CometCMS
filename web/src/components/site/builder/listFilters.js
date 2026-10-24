/** Field types and the control visitors get for them; mirrors ListFilters on the server. */
const FILTER_KINDS = {
  boolean: "toggle",
  select: "options",
  relation: "options",
  text: "search",
  slug: "search",
  textarea: "search",
  markdown: "search",
  html: "search",
  email: "search",
  url: "search",
  number: "range",
  range: "range",
  date: "range",
  datetime: "range",
};

const SORT_TYPES = ["text", "slug", "select", "boolean", "number", "range", "date", "datetime"];
const DATE_FIELDS = ["published_at", "created_at", "updated_at"];

const label = (key, config) => config?.label || key;

/** Fields of a content type visitors can filter by, each with its control kind. */
export function filterFields(type, t) {
  const fields = Object.entries(type?.fields ?? {})
    .filter(([key, config]) => key !== "slug" && FILTER_KINDS[config?.type])
    .map(([key, config]) => ({ value: key, label: label(key, config), kind: FILTER_KINDS[config.type] }));
  return [
    { value: "title", label: t("site.systemField.title"), kind: "search" },
    ...fields,
    { value: "published_at", label: t("site.systemField.published_at"), kind: "range" },
  ];
}

/** Fields visitors can sort by. */
export function sortFields(type, t) {
  return [
    ...DATE_FIELDS.map((key) => ({ value: key, label: t(`site.systemField.${key}`) })),
    { value: "title", label: t("site.systemField.title") },
    ...Object.entries(type?.fields ?? {})
      .filter(([key, config]) => key !== "slug" && SORT_TYPES.includes(config?.type))
      .map(([key, config]) => ({ value: key, label: label(key, config) })),
  ];
}
