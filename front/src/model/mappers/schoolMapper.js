/** @typedef {import("../dto/schoolDto.js").SchoolDto} SchoolDto */

/**
 * @typedef {object} School
 * @property {number} id
 * @property {string} name
 * @property {string|null} shortName
 * @property {string} emailDomain   - en minuscules, comme le back
 * @property {string|null} city
 */

/** @param {SchoolDto} dto @returns {School} */
export function toSchool(dto) {
  return {
    id: dto.id,
    name: String(dto.name ?? ""),
    shortName: dto.short_name ?? null,
    emailDomain: String(dto.email_domain ?? "").toLowerCase(),
    city: dto.city ?? null,
  };
}

/** @param {SchoolDto[]} dtos @returns {School[]} */
export function toSchools(dtos) {
  return dtos.map(toSchool);
}
