import { toPageQuery } from "../pageQueryMapper.js";

/** @param {{ limit?: number, offset?: number }} [page] */
export function toFaqQuery(page = {}) {
  return toPageQuery(page);
}
