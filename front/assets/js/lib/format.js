const LOCALE = "fr-FR";

/**
 * Formate une date (ISO 8601, timestamp ou Date) pour l'affichage.
 * Valeur vide ou invalide -> `fallback` (jamais "Invalid Date").
 */
export function formatDate(value, {
  fallback = "",
  locale = LOCALE,
  options = { dateStyle: "medium", timeStyle: "short" },
} = {}) {
  if (value === null || value === undefined || value === "") return fallback;
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return fallback;
  return date.toLocaleString(locale, options);
}

/**
 * Formate un prix stocke en centimes. `isPaid: false` -> `freeLabel`.
 * Montant non numerique -> `fallback`.
 */
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
  try {
    return amount.toLocaleString(locale, { style: "currency", currency: currency || "EUR" });
  } catch {
    // Code devise invalide (RangeError) : affichage brut plutot que de casser le rendu.
    return `${amount.toLocaleString(locale, { minimumFractionDigits: 2 })} ${currency}`;
  }
}
