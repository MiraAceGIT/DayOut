const LOCALE = "fr-FR";
export const EVENT_TIME_ZONE = "Europe/Paris";

function toDate(value) {
  if (value === null || value === undefined || value === "") return null;
  const date = value instanceof Date ? value : new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

/**
 * @param {Date|string|number|null|undefined} value
 * @param {{ fallback?: string, locale?: string, options?: Intl.DateTimeFormatOptions }} [opts]
 */
export function formatDate(value, {
  fallback = "",
  locale = LOCALE,
  options = { dateStyle: "medium", timeStyle: "short" },
} = {}) {
  const date = toDate(value);
  return date ? date.toLocaleString(locale, { timeZone: EVENT_TIME_ZONE, ...options }) : fallback;
}

export function formatDay(value, { locale = LOCALE, timeZone = EVENT_TIME_ZONE, withYear = false } = {}) {
  const date = toDate(value);
  if (!date) return "";
  const text = date.toLocaleDateString(locale, {
    weekday: "short", day: "numeric", month: "short", timeZone,
    ...(withYear ? { year: "numeric" } : {}),
  });
  return text.charAt(0).toUpperCase() + text.slice(1);
}

export function formatHour(value, { timeZone = EVENT_TIME_ZONE } = {}) {
  const date = toDate(value);
  if (!date) return "";
  const parts = new Intl.DateTimeFormat(LOCALE, {
    hour: "numeric", minute: "2-digit", hourCycle: "h23", timeZone,
  }).formatToParts(date);
  const hour = Number(parts.find(part => part.type === "hour").value);
  const minute = parts.find(part => part.type === "minute").value;
  return minute === "00" ? `${hour} h` : `${hour} h ${minute}`;
}

export function formatEventWhen(startAt, endAt, { withEnd = false, withYear = false, timeZone = EVENT_TIME_ZONE } = {}) {
  const start = toDate(startAt);
  if (!start) return "";
  const end = withEnd ? toDate(endAt) : null;
  const year = new Intl.DateTimeFormat(LOCALE, { year: "numeric", timeZone });
  const showYear = withYear || Boolean(end && year.format(start) !== year.format(end));
  let text = `${formatDay(start, { timeZone, withYear: showYear })} · ${formatHour(start, { timeZone })}`;
  if (end && end >= start) {
    const day = new Intl.DateTimeFormat(LOCALE, { year: "numeric", month: "numeric", day: "numeric", timeZone });
    const endDate = day.format(start) === day.format(end) ? "" : `${formatDay(end, { timeZone, withYear: showYear })} · `;
    text += ` – ${endDate}${formatHour(end, { timeZone })}`;
  }
  return text;
}

/** Libelle partage par les cartes et le detail ; le calcul reste dans model/domain/Event.js.
 * @param {number|null} remaining
 */
export function formatRemainingPlaces(remaining) {
  if (remaining === null) return "Places illimitées";
  if (remaining === 0) return "Complet";
  return `${pluralize(remaining, "place")} restante${remaining > 1 ? "s" : ""}`;
}

export function formatDistance(km, { locale = LOCALE } = {}) {
  if (!Number.isFinite(km)) return "";
  const digits = km < 10 ? 1 : 0;
  return `${km.toLocaleString(locale, { minimumFractionDigits: digits, maximumFractionDigits: digits })} km`;
}

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

export function pluralize(n, word) {
  return `${n} ${word}${n > 1 ? "s" : ""}`;
}

export function formatCoordinates({ lat, lng }, { locale = LOCALE } = {}) {
  const coord = (n) => n.toLocaleString(locale, { maximumFractionDigits: 4 });
  return `${coord(lat)} ; ${coord(lng)}`;
}
