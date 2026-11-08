// Identifiant decimal positif, compatible avec les entiers 64 bits du backend PHP.
// Rester en chaine evite toute perte de precision au-dela de Number.MAX_SAFE_INTEGER.
const MAX_EVENT_ID = "9223372036854775807";

/** @param {string|null} value */
export function parseEventId(value) {
  if (typeof value !== "string" || value.trim() !== value || !/^[1-9][0-9]*$/.test(value)) return null;
  if (value.length > MAX_EVENT_ID.length || (value.length === MAX_EVENT_ID.length && value > MAX_EVENT_ID)) return null;
  return value;
}
