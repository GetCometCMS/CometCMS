import { describe, expect, it } from "vitest";
import {
  apiSortKey,
  boolValue,
  effectiveStatus,
  fieldColumnKey,
  fieldLabel,
  fieldTextValue,
  fieldValue,
  formatNumberField,
  formatSelectField,
  humanizeKey,
  mediaValuesFor,
  normalizeFieldSortValue,
  orderLocales,
  trimText,
} from "./contentDisplay.js";

describe("contentDisplay", () => {
  it("resolves display status and locale order", () => {
    expect(effectiveStatus({ status: "published", published_at: "2999-01-01T00:00:00Z" })).toBe("scheduled");
    expect(orderLocales(["de", "en", "de"], "en")).toEqual(["en", "de"]);
  });

  it("builds field sort and label values", () => {
    expect(fieldColumnKey("summary")).toBe("field:summary");
    expect(apiSortKey("field:summary")).toBe("summary");
    expect(humanizeKey("hero_title")).toBe("Hero Title");
    expect(fieldLabel("hero_title", { label: "Hero" })).toBe("Hero");
  });

  it("reads and sorts field values", () => {
    expect(fieldValue({ data: { views: 4 } }, "views")).toBe(4);
    expect(normalizeFieldSortValue(["B", "a"])).toBe("b a");
    expect(normalizeFieldSortValue(true)).toBe(1);
  });

  it("formats booleans, numbers, select labels, and text", () => {
    expect(boolValue("yes")).toBe(true);
    expect(formatNumberField("12.345", { display_decimals: 2 })).toBe("12.35");
    expect(formatNumberField("12.345", { display_decimals: 2 }, "de")).toBe("12,35");
    expect(formatSelectField(["draft", "published"], { options: { draft: "Draft", published: "Published" } }, false)).toBe("Draft, Published");
    expect(fieldTextValue(["a", "b"], (value) => (value ? "True" : "False"), false)).toBe("a, b");
    expect(trimText("x".repeat(82))).toHaveLength(80);
  });

  it("normalizes media field values for list previews", () => {
    expect(mediaValuesFor({ gallery: [" hero.jpg ", null, "logo.png"] }, "gallery")).toEqual(["hero.jpg", "logo.png"]);
    expect(mediaValuesFor({ hero: "hero.jpg" }, "hero")).toEqual(["hero.jpg"]);
  });
});
