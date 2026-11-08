/**
 * @typedef {object} CreatorDto
 * @property {number} id
 * @property {string} display_name
 * @property {string|null} description
 * @property {string|null} avatar_url
 * @property {string|null} school_name
 */

/** Detail de GET /events/{id}, les champs du feed plus le profil public du createur.
 * @typedef {import("./eventDto.js").EventDto & {creator: CreatorDto}} EventDetailDto
 */
export {};
