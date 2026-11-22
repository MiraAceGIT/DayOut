import { api } from "../../core/api.js";
import { toCategory } from "../mappers/categoryMapper.js";

/** @typedef {ReturnType<typeof toCategory>} Category */

/**
 * GET /categories : categories actives, triees par libelle (centres d'interet de l'inscription).
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<Category[]>}
 */
export async function fetchCategories({ signal } = {}) {
  const categories = await api.get("/categories", { signal });
  if (!Array.isArray(categories)) throw new TypeError("Invalid categories response");
  return categories.map(toCategory);
}
