/** @typedef {import("./categoryDto.js").CategoryDto} CategoryDto */

/**
 * @typedef {object} EventDto
 * @property {number} id
 * @property {string} title
 * @property {string} slug
 * @property {string} description
 * @property {string|null} cover_image_url
 * @property {string} start_at            ISO 8601 UTC
 * @property {string} end_at              ISO 8601 UTC
 * @property {string|null} venue_name
 * @property {string|null} address_line
 * @property {string|null} city
 * @property {string|null} postal_code
 * @property {number|null} latitude
 * @property {number|null} longitude
 * @property {number|null} capacity       null = illimitee
 * @property {number} registered_count
 * @property {number} price_cents
 * @property {string} currency
 * @property {boolean} is_paid
 * @property {string|null} external_ticket_url
 * @property {boolean} school_restricted
 * @property {CategoryDto[]} categories
 */

/**
 * Reponse de GET /events (EventController::index).
 * @typedef {object} EventPageDto
 * @property {EventDto[]} items
 * @property {number} limit
 * @property {number} offset
 * @property {boolean} has_more
 */

export {};
