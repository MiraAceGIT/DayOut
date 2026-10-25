const LOCALE = "fr-FR";

function toDate(value) {
  if (value === null || value === undefined || value === "") return null;
  const date = value instanceof Date ? value : new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

/**
 * Valeur vide ou invalide -> `fallback` (jamais "Invalid Date").
 * @param {Date|string|number|null|undefined} value
 * @param {{ fallback?: string, locale?: string, options?: Intl.DateTimeFormatOptions }} [opts]
 */
export function formatDate(value, {
  fallback = "",
  locale = LOCALE,
  options = { dateStyle: "medium", timeStyle: "short" },
} = {}) {
  const date = toDate(value);
  return date ? date.toLocaleString(locale, options) : fallback;
}

/** "Sam. 15 nov." */
export function formatDay(value, { locale = LOCALE } = {}) {
  const date = toDate(value);
  if (!date) return "";
  const text = date.toLocaleDateString(locale, { weekday: "short", day: "numeric", month: "short" });
  return text.charAt(0).toUpperCase() + text.slice(1);
}

/** "9 h", "18 h 30" (typographie francaise de la charte). */
export function formatHour(value) {
  const date = toDate(value);
  if (!date) return "";
  const minutes = date.getMinutes();
  return minutes ? `${date.getHours()} h ${String(minutes).padStart(2, "0")}` : `${date.getHours()} h`;
}

/** "Sam. 15 nov. · 9 h" ou, si la fin est le meme jour, "Sam. 15 nov. · 9 h – 21 h". */
export function formatEventWhen(startAt, endAt, { withEnd = false } = {}) {
  const start = toDate(startAt);
  if (!start) return "";
  let text = `${formatDay(start)} · ${formatHour(start)}`;
  const end = withEnd ? toDate(endAt) : null;
  if (end && end.toDateString() === start.toDateString()) text += ` – ${formatHour(end)}`;
  return text;
}

/** "0,6 km", "12 km" ; non numerique -> "". */
export function formatDistance(km, { locale = LOCALE } = {}) {
  if (!Number.isFinite(km)) return "";
  const digits = km < 10 ? 1 : 0;
  return `${km.toLocaleString(locale, { minimumFractionDigits: digits, maximumFractionDigits: digits })} km`;
}

/** `cents` en centimes ; `isPaid: false` -> `freeLabel`, montant non numerique -> `fallback`. */
export function formatPrice(cents, {
  isPaid = true,
  currency = "EUR",
  freeLabel = "Gratuit",
  fallback = "",
  locale = LOCALE,
} = {}) {
  if (!isPaid) return freeLabel;
  const amount = Number(cents) / 100;
  if (cents === null || cents === undefined || cents === "" || !Number.isFinite(amount)) return fallback;
  const digits = Number.isInteger(amount) ? 0 : 2;
  try {
    return amount.toLocaleString(locale, {
      style: "currency",
      currency: currency || "EUR",
      minimumFractionDigits: digits,
      maximumFractionDigits: 2,
    });
  } catch {
    // Code devise invalide (RangeError) : affichage brut plutot que de casser le rendu.
    return `${amount.toLocaleString(locale, { minimumFractionDigits: digits })} ${currency}`;
  }
}

/** "1 place", "3 places" : pluriel regulier en "s". */
export function pluralize(n, word) {
  return `${n} ${word}${n > 1 ? "s" : ""}`;
}

/** { lat, lng } -> "48,8455 ; 2,3275" (4 decimales au plus). */
export function formatCoordinates({ lat, lng }, { locale = LOCALE } = {}) {
  const coord = (n) => n.toLocaleString(locale, { maximumFractionDigits: 4 });
  return `${coord(lat)} ; ${coord(lng)}`;
}
