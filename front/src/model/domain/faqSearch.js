import { normalize } from "../../shared/text.js";

/**
 * @template {{ question: string, answer: string }} T
 * @param {T[]} entries
 * @param {string} [query]
 * @returns {T[]}
 */
export function searchFaq(entries, query = "") {
  const needle = normalize(query);
  if (!needle) return entries;
  return entries.filter((entry) => normalize(entry.question).includes(needle) || normalize(entry.answer).includes(needle));
}
