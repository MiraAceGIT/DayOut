/** @param {number} shown @param {number} total @param {string} query */
export function faqStatus(shown, total, query) {
  if (total === 0) return "Aucune question disponible pour le moment.";
  const keyword = query.trim();
  if (!keyword) return "";
  if (shown === 0) return `Aucune question ne correspond à « ${keyword} ».`;
  return `${shown} question${shown > 1 ? "s" : ""} sur ${total} correspond${shown > 1 ? "ent" : ""} à « ${keyword} ».`;
}
