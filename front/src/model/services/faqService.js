import { api } from "../../core/api.js";
import { MAX_PAGE_LIMIT } from "../../core/apiLimits.js";
import { toFaqEntries } from "../mappers/faqMapper.js";
import { toFaqQuery } from "../mappers/faqRequestMapper.js";

/** @typedef {import("../dto/faqDto.js").FaqPageDto} FaqPageDto */
/** @typedef {import("../mappers/faqMapper.js").FaqEntry} FaqEntry */

/** Toutes les questions actives, page par page, dans l'ordre d'affichage.
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<FaqEntry[]>}
 */
export async function fetchFaq({ signal } = {}) {
  /** @type {FaqEntry[]} */
  const entries = [];
  for (let offset = 0; ; offset += MAX_PAGE_LIMIT) {
    const query = toFaqQuery({ limit: MAX_PAGE_LIMIT, offset });
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
