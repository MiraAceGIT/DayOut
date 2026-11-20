/** @typedef {import("../dto/faqDto.js").FaqDto} FaqDto */

/**
 * @typedef {object} FaqEntry
 * @property {number} id
 * @property {string} question
 * @property {string} answer
 * @property {number} sortOrder
 * @property {boolean} active
 */

/** @param {FaqDto} dto @returns {FaqEntry} */
export function toFaqEntry(dto) {
  return {
    id: dto.id,
    question: String(dto.question ?? ""),
    answer: String(dto.answer ?? ""),
    sortOrder: dto.sort_order ?? 0,
    active: dto.active ?? true,
  };
}

/** @param {FaqDto[]} dtos @returns {FaqEntry[]} */
export function toFaqEntries(dtos) {
  return dtos.map(toFaqEntry);
}

/** @param {FaqEntry} entry @returns {FaqDto} */
export function toFaqDto(entry) {
  return {
    id: entry.id,
    question: entry.question,
    answer: entry.answer,
    sort_order: entry.sortOrder,
    active: entry.active,
  };
}
