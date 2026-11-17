import { api } from "../../core/api.js";

/** @typedef {{ id: number, question: string, answer: string }} FaqEntry */

/** @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<FaqEntry[]>}
 */
export async function fetchFaq({ signal } = {}) {
  const entries = [];
  let offset = 0;
  while (true) {
    const page = await api.get(`/faq?limit=100&offset=${offset}`, { signal });
    if (!page || !Array.isArray(page.items) || !page.items.every(entry =>
      entry && Number.isSafeInteger(entry.id) && entry.id > 0
      && typeof entry.question === "string" && typeof entry.answer === "string"
    ) || page.limit !== 100 || page.offset !== offset
      || !Number.isSafeInteger(page.total) || page.total < 0
      || typeof page.has_more !== "boolean" || page.items.length > page.limit
      || (page.has_more && page.items.length === 0)) {
      throw new TypeError("Invalid FAQ response");
    }
    entries.push(...page.items);
    if (!page.has_more) return entries;
    offset += page.limit;
  }
}
