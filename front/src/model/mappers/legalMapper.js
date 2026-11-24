/** @typedef {import("../dto/legalDto.js").LegalPageDto} LegalPageDto */

/**
 * @param {LegalPageDto} dto
 */
export function toLegalPage(dto) {
  const updatedDate = dto.updated_at ? new Date(dto.updated_at) : null;
  const formattedDate =
    updatedDate && !Number.isNaN(updatedDate.getTime())
      ? updatedDate.toLocaleDateString("fr-FR", {
          day: "numeric",
          month: "long",
          year: "numeric",
        })
      : "";

  return {
    slug: dto.slug,
    title: dto.title ?? "",
    bodyMd: dto.body_md ?? "",
    updatedAtLabel: formattedDate ? `Dernière mise à jour le ${formattedDate}` : "",
  };
}
