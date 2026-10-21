import { describe, expect, it } from "vitest";
import {
  buildEndpointPath,
  buildQueryItems,
  encodeQueryKey,
  fieldKind,
  fieldLabel,
  normalizeQueryValue,
  operatorsForField,
} from "./apiQueryBuilderUtils.js";

describe("apiQueryBuilderUtils", () => {
  it("builds endpoint paths for content, content types, and media", () => {
    expect(buildEndpointPath({ selectedResource: "" })).toBe("/{resource}");
    expect(buildEndpointPath({ selectedResource: "media" })).toBe("/media");
    expect(buildEndpointPath({ selectedResource: "content-types", typeMode: "single", collectionName: "case studies" })).toBe("/content-types/case%20studies");
    expect(buildEndpointPath({ selectedResource: "content", collectionName: "posts", contentMode: "single", identifier: "hello world" })).toBe("/content/posts/hello%20world");
  });

  it("builds query items with filters and custom includes", () => {
    expect(buildQueryItems({
      selectedResource: "content",
      contentMode: "list",
      limit: "20",
      offset: "0",
      sort: "-created_at",
      search: "launch",
      filterField: "views",
      filterOperator: "gte",
      filterValue: "10",
      include: "__custom",
      customInclude: "author",
      locale: "en",
    })).toEqual([
      ["limit", "20"],
      ["offset", "0"],
      ["sort", "-created_at"],
      ["q", "launch"],
      ["filter[views][gte]", "10"],
      ["include", "author"],
      ["locale", "en"],
    ]);
  });

  it("normalizes query values and field metadata", () => {
    expect(normalizeQueryValue([" a ", "", "b"])).toBe("a,b");
    expect(encodeQueryKey("filter[views][gte]")).toBe("filter[views][gte]");
    expect(fieldLabel("hero_title")).toBe("Hero Title");
    expect(fieldKind("relation")).toBe("text");
    expect(operatorsForField({ kind: "number" }).map((operator) => operator.value)).toContain("gte");
  });
});
