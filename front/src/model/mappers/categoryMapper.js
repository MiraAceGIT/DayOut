/** @typedef {import("../dto/categoryDto.js").CategoryDto} CategoryDto */

export function toCategory(dto) {
  const slug = String(dto?.slug ?? "");
  return {
    slug,
    label: String(dto?.label ?? slug),
  };
}

/** @returns {CategoryDto} */
export function toCategoryDto(category) {
  return { slug: category.slug, label: category.label };
}
