export const statusPillClasses = {
  draft: "bg-slate-100 text-slate-700 ring-slate-200",
  published: "bg-emerald-100 text-emerald-700 ring-emerald-200",
  scheduled: "bg-sky-100 text-sky-700 ring-sky-200",
  protected: "bg-violet-100 text-violet-700 ring-violet-200",
  archived: "bg-amber-100 text-amber-700 ring-amber-200",
};

export function effectiveStatus(entry, now = new Date()) {
  if (
    entry.status === "published" &&
    entry.published_at &&
    new Date(entry.published_at) > now
  ) {
    return "scheduled";
  }
  return entry.status;
}

export function statusPillClass(status) {
  return [
    "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ring-1 ring-inset",
    statusPillClasses[status] ?? "bg-slate-100 text-slate-600 ring-slate-200",
  ];
}

export function orderLocales(locales, defaultLocale) {
  const unique = [...new Set(locales)];
  if (!defaultLocale || !unique.includes(defaultLocale)) return unique;
  return [defaultLocale, ...unique.filter((loc) => loc !== defaultLocale)];
}

export function fieldColumnKey(key) {
  return `field:${key}`;
}

export function isFieldSortKey(key) {
  return typeof key === "string" && key.startsWith("field:");
}

export function fieldKeyFromColumnKey(key) {
  return String(key).slice("field:".length);
}

export function apiSortKey(key) {
  return isFieldSortKey(key) ? fieldKeyFromColumnKey(key) : key;
}

export function humanizeKey(key) {
  return String(key)
    .replace(/[_-]+/g, " ")
    .replace(/\s+/g, " ")
    .trim()
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

export function fieldLabel(key, config) {
  if (config?.label) return String(config.label);
  return humanizeKey(key);
}

export function fieldValue(entry, key) {
  if (entry && Object.prototype.hasOwnProperty.call(entry, key))
    return entry[key];
  if (entry?.data && Object.prototype.hasOwnProperty.call(entry.data, key))
    return entry.data[key];
  return null;
}

export function normalizeFieldSortValue(value) {
  if (value === null || value === undefined) return "";
  if (typeof value === "number") return value;
  if (typeof value === "boolean") return value ? 1 : 0;
  if (Array.isArray(value))
    return value.map((item) => normalizeFieldSortValue(item)).join(" ");
  if (typeof value === "object") return JSON.stringify(value);
  const number = Number(value);
  return Number.isFinite(number) && String(value).trim() !== ""
    ? number
    : String(value).toLowerCase();
}

export function boolValue(value) {
  if (typeof value === "string")
    return ["true", "1", "yes", "on"].includes(value.toLowerCase());
  return !!value;
}

export function booleanPillClass(value) {
  return [
    "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset",
    boolValue(value)
      ? "bg-emerald-50 text-emerald-700 ring-emerald-200"
      : "bg-slate-100 text-slate-600 ring-slate-200",
  ];
}

export function formatNumberField(value, config = null, locale = "en") {
  if (value === null || value === undefined || value === "") return "—";
  const number = Number(value);
  if (!Number.isFinite(number)) return String(value);

  const decimals = config?.display_decimals;
  if (decimals === "full") return String(value);

  const fixedDigits =
    decimals === null || decimals === undefined || decimals === ""
      ? 0
      : Number(decimals);
  return Number.isInteger(fixedDigits) && fixedDigits >= 0 && fixedDigits <= 3
    ? new Intl.NumberFormat(locale, {
        minimumFractionDigits: fixedDigits,
        maximumFractionDigits: fixedDigits,
      }).format(number)
    : new Intl.NumberFormat(locale).format(number);
}

export function selectOptionMap(config = {}) {
  const options = config?.options;
  if (options && typeof options === "object" && !Array.isArray(options)) {
    return Object.fromEntries(
      Object.entries(options).map(([key, label]) => [
        String(key),
        String(label),
      ]),
    );
  }

  return Object.fromEntries(
    (options ?? []).map((option) => [String(option), String(option)]),
  );
}

export function formatSelectField(value, config = {}, trimmed = true) {
  if (value === null || value === undefined || value === "") return "—";

  const labels = selectOptionMap(config);
  const labelFor = (item) => {
    const key = String(item ?? "").trim();
    return key ? (labels[key] ?? key) : "";
  };

  const text = Array.isArray(value)
    ? value.map(labelFor).filter(Boolean).join(", ")
    : labelFor(value);

  if (!text) return "—";
  return trimmed ? trimText(text) : text;
}

export function fieldTextValue(value, formatBoolean, trimmed = true) {
  if (value === null || value === undefined || value === "") return "—";
  if (typeof value === "boolean") return formatBoolean(value);
  if (Array.isArray(value)) {
    const text = value
      .map((item) => fieldTextValue(item, formatBoolean, false))
      .filter((item) => item !== "—")
      .join(", ");
    return trimmed ? trimText(text) : text;
  }
  if (typeof value === "object") {
    return Array.isArray(value) ? "[…]" : "{…}";
  }

  const text = String(value).replace(/\s+/g, " ").trim();
  return trimmed ? trimText(text) : text;
}

export function trimText(value, max = 80) {
  const text = String(value ?? "");
  return text.length > max ? `${text.slice(0, max - 1)}…` : text;
}

export function mediaValuesFor(entry, key) {
  const value = fieldValue(entry, key);
  if (Array.isArray(value)) {
    return value.map((item) => String(item ?? "").trim()).filter(Boolean);
  }

  const single = String(value ?? "").trim();
  return single ? [single] : [];
}
