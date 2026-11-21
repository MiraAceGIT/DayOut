import { MAX_KEYWORD_LENGTH } from "../../core/apiLimits.js";

/** @param {unknown} query */
export function cleanKeyword(query) {
  return String(query ?? "").trim().slice(0, MAX_KEYWORD_LENGTH);
}
