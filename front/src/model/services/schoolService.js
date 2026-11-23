import { api } from "../../core/api.js";
import { toSchools } from "../mappers/schoolMapper.js";

/** @typedef {import("../mappers/schoolMapper.js").School} School */

/**
 * GET /schools : ecoles actives, triees par nom (petit referentiel, sans pagination).
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<School[]>}
 */
export async function fetchSchools({ signal } = {}) {
  const schools = await api.get("/schools", { signal });
  if (!Array.isArray(schools)) throw new TypeError("Invalid schools response");
  return toSchools(schools);
}
