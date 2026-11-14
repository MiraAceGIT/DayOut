import { toEvent } from "./eventMapper.js";
import { MAX_EVENTS_LIMIT, MAX_EVENTS_OFFSET } from "./eventQueryMapper.js";

/** Seules les violations du contrat sont converties en ApiError, pas les bugs du mapper. */
export class EventResponseError extends TypeError {}

const isRecord = value => value !== null && typeof value === "object" && !Array.isArray(value);
const isCount = value => Number.isSafeInteger(value) && value >= 0;
const isId = value => Number.isSafeInteger(value) && value > 0;
const isDate = value => typeof value === "string" && Number.isFinite(Date.parse(value));
const nullableText = value => value === null || typeof value === "string";

function requireContract(condition, field) {
  if (!condition) throw new EventResponseError(`Invalid event response: ${field}`);
}

function validateCreator(creator) {
  requireContract(isRecord(creator), "creator");
  requireContract(isId(creator.id) && typeof creator.display_name === "string"
    && ["description", "avatar_url", "school_name"].every(key => nullableText(creator[key])), "creator fields");
}

/** Validation a la frontiere API. Les champs supplementaires restent acceptes.
 * @param {import("../dto/eventDto.js").EventDto} dto
 * @param {string} [expectedId]
 */
export function readEventResponse(dto, expectedId) {
  requireContract(isRecord(dto), "object");
  requireContract(isId(dto.id) && (expectedId === undefined || String(dto.id) === expectedId), "id");
  requireContract(["title", "slug", "description", "currency"].every(key => typeof dto[key] === "string"), "text fields");
  requireContract(["cover_image_url", "venue_name", "address_line", "city", "postal_code", "external_ticket_url"]
    .every(key => nullableText(dto[key])), "nullable text fields");
  requireContract(isDate(dto.start_at) && isDate(dto.end_at), "dates");
  requireContract((dto.capacity === null || isId(dto.capacity)) && isCount(dto.registered_count), "capacity");
  requireContract(isCount(dto.price_cents) && typeof dto.is_paid === "boolean", "price");
  requireContract(typeof dto.school_restricted === "boolean"
    && ["draft", "published", "cancelled", "archived"].includes(dto.status), "status");
  requireContract(Array.isArray(dto.categories) && dto.categories.every(category => isRecord(category)
    && typeof category.slug === "string" && typeof category.label === "string"), "categories");
  validateCreator(dto.creator);
  return toEvent(dto);
}

/** @param {import("../dto/eventDto.js").EventPageDto} dto */
export function toEventPage(dto) {
  requireContract(isRecord(dto) && Array.isArray(dto.items), "page items");
  requireContract(isId(dto.limit) && dto.limit <= MAX_EVENTS_LIMIT
    && isCount(dto.offset) && dto.offset <= MAX_EVENTS_OFFSET
    && typeof dto.has_more === "boolean" && isCount(dto.total), "pagination");
  const nextOffset = dto.offset + dto.items.length;
  // COUNT et SELECT sont deux lectures cote back : total peut varier pendant la requete.
  requireContract(dto.items.length <= dto.limit
    && dto.has_more === (nextOffset < dto.total)
    && (!dto.has_more || dto.items.length > 0), "page progression");
  const items = dto.items.map(dto => readEventResponse(dto));
  requireContract(new Set(items.map(event => event.id)).size === items.length, "duplicate IDs");
  return { items, limit: dto.limit, offset: dto.offset, hasMore: dto.has_more, total: dto.total };
}
