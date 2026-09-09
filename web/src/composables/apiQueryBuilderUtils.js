export const apiQueryResources = [
  {
    value: "content",
    label: "Content",
    description: "List entries or fetch one item",
    icon: "mdi:folder-outline",
  },
  {
    value: "content-types",
    label: "Content Types",
    description: "Read schemas for your frontend",
    icon: "mdi:cube-outline",
  },
  {
    value: "media",
    label: "Media",
    description: "List uploaded files",
    icon: "mdi:image-multiple-outline",
  },
  {
    value: "submissions",
    label: "External Submissions",
    description: "Submit to an enabled collection",
    icon: "mdi:inbox-arrow-down-outline",
  },
];

export function buildEndpointPath({
  selectedResource,
  typeMode,
  collectionName,
  identifier,
  isActiveSingleton,
  contentMode,
}) {
  if (!selectedResource) {
    return "/{resource}";
  }

  if (selectedResource === "content-types") {
    return typeMode === "single" && collectionName
      ? `/content-types/${encodeURIComponent(collectionName)}`
      : "/content-types";
  }

  if (selectedResource === "media") {
    return "/media";
  }

  if (selectedResource === "submissions") {
    const collection = collectionName || "{collection}";
    return `/content/${encodeURIComponent(collection)}/submissions`;
  }

  const collection = collectionName || "{collection}";
  const base = `/content/${encodeURIComponent(collection)}`;
  const item = String(identifier ?? "").trim();

  if (isActiveSingleton) {
    return base;
  }

  return contentMode === "single" && item !== ""
    ? `${base}/${encodeURIComponent(item)}`
    : base;
}

export function buildQueryItems({
  selectedResource,
  contentMode,
  limit,
  offset,
  sort,
  search,
  filterField,
  filterOperator,
  filterValue,
  include,
  customInclude,
  locale,
  mediaCategory,
}) {
  const items = [];

  if (selectedResource === "content") {
    const includeValue = include === "__custom" ? customInclude : include;

    if (contentMode === "list") {
      addQuery(items, "limit", limit);
      addQuery(items, "offset", offset);
      addQuery(items, "sort", sort);
      addQuery(items, "q", search);

      if (filterField && hasFilterValue(filterValue)) {
        const key =
          filterOperator === "eq"
            ? `filter[${filterField}]`
            : `filter[${filterField}][${filterOperator}]`;
        addQuery(items, key, filterValue);
      }
    }

    addQuery(items, "include", includeValue);
    addQuery(items, "locale", locale);
  }

  if (selectedResource === "media") {
    addQuery(items, "limit", limit);
    addQuery(items, "offset", offset);
    addQuery(items, "q", search);
    addQuery(items, "category", mediaCategory);
  }

  return items;
}

export function addQuery(items, key, value) {
  const normalized = normalizeQueryValue(value);
  if (normalized !== "") {
    items.push([key, normalized]);
  }
}

export function normalizeQueryValue(value) {
  if (Array.isArray(value)) {
    return value
      .map((item) => String(item ?? "").trim())
      .filter(Boolean)
      .join(",");
  }

  return String(value ?? "").trim();
}

export function hasFilterValue(value) {
  return normalizeQueryValue(value) !== "";
}

export function fieldLabel(key, config) {
  if (config?.label) return String(config.label);
  return String(key)
    .replace(/[_-]+/g, " ")
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

export function fieldKind(type) {
  return [
    "text",
    "slug",
    "textarea",
    "html",
    "number",
    "range",
    "boolean",
    "date",
    "datetime",
    "select",
  ].includes(type)
    ? type
    : "text";
}

export function operatorsForField(field) {
  if (!field) return [{ value: "eq", label: "=" }];

  if (["number", "range", "date", "datetime"].includes(field.kind)) {
    return [
      { value: "eq", label: "=" },
      { value: "ne", label: "!=" },
      { value: "gt", label: ">" },
      { value: "gte", label: ">=" },
      { value: "lt", label: "<" },
      { value: "lte", label: "<=" },
      { value: "in", label: "in" },
    ];
  }

  if (field.kind === "boolean") {
    return [
      { value: "eq", label: "=" },
      { value: "ne", label: "!=" },
    ];
  }

  if (field.kind === "select" || field.config?.multiple) {
    return [
      { value: "eq", label: "=" },
      { value: "ne", label: "!=" },
      { value: "in", label: "in" },
      { value: "contains", label: "contains" },
    ];
  }

  return [
    { value: "eq", label: "=" },
    { value: "ne", label: "!=" },
    { value: "contains", label: "contains" },
    { value: "in", label: "in" },
  ];
}

export function encodeQueryKey(key) {
  return encodeURIComponent(key).replace(/%5B/g, "[").replace(/%5D/g, "]");
}
