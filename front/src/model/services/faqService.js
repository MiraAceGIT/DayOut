import { api } from "../../core/api.js";

/** @typedef {{ id: number, question: string, answer: string }} FaqEntry */

/** @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<FaqEntry[]>}
 */
export async function fetchFaq({ signal } = {}) {
  const entries = await api.get("/faq", { signal });
  if (!Array.isArray(entries) || !entries.every(entry =>
    entry && Number.isSafeInteger(entry.id) && entry.id > 0
    && typeof entry.question === "string" && typeof entry.answer === "string"
  )) {
    throw new TypeError("Invalid FAQ response");
  }
  return entries;
}
