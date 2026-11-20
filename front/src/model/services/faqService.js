import { api } from "../../core/api.js";
import { toFaqEntries } from "../mappers/faqMapper.js";

/** @typedef {import("../dto/faqDto.js").FaqPageDto} FaqPageDto */
/** @typedef {import("../mappers/faqMapper.js").FaqEntry} FaqEntry */

/** Plafond de `limit` cote API (back : FaqFindQuery::RULES). */
export const MAX_FAQ_LIMIT = 100;

/** Toutes les questions actives, page par page, dans l'ordre d'affichage.
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<FaqEntry[]>}
 */
export async function fetchFaq({ signal } = {}) {
  /** @type {FaqEntry[]} */
  const entries = [];
  for (let offset = 0; ; offset += MAX_FAQ_LIMIT) {
    const query = new URLSearchParams({ limit: String(MAX_FAQ_LIMIT), offset: String(offset) });
    /** @type {FaqPageDto} */
    const page = await api.get(`/faq?${query}`, { signal });
    // Une page vide annoncee "has_more" bouclerait sans fin.
    if (!Array.isArray(page?.items) || (page.has_more && page.items.length === 0)) {
      throw new TypeError("Invalid FAQ response");
    }
    entries.push(...toFaqEntries(page.items));
    if (!page.has_more) return entries;
  }
}
