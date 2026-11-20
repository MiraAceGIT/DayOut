// Format JSON de l'API (snake_case), miroir de back/src/Model/Dto/Faq/FaqDto.php.

/**
 * @typedef {object} FaqDto
 * @property {number} id
 * @property {string} question
 * @property {string} answer
 * @property {number} sort_order
 * @property {boolean} active
 */

/**
 * Reponse de GET /faq (FaqController::findMany).
 * @typedef {object} FaqPageDto
 * @property {FaqDto[]} items
 * @property {number} limit
 * @property {number} offset
 * @property {boolean} has_more
 * @property {number} total
 */

export {};
