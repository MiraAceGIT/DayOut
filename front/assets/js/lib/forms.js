// lib/ ne depend pas de core/ : ApiError est reconnue par son `name`, pas par instanceof.

const ERROR_CLASS = "field-error";

function errorIdFor(field) {
  return `${String(field).replace(/\s+/g, "-")}-error`;
}

function addToken(el, attr, token) {
  const tokens = (el.getAttribute(attr) || "").split(/\s+/).filter(Boolean);
  if (!tokens.includes(token)) tokens.push(token);
  el.setAttribute(attr, tokens.join(" "));
}

function removeToken(el, attr, token) {
  const tokens = (el.getAttribute(attr) || "").split(/\s+/).filter((t) => t && t !== token);
  if (tokens.length) el.setAttribute(attr, tokens.join(" "));
  else el.removeAttribute(attr);
}

function controlsFor(form, field) {
  const item = form.elements.namedItem(field);
  if (!item) return [];
  // RadioNodeList (groupe radio / checkbox de meme name) ou element simple.
  return item instanceof Element ? [item] : Array.from(item);
}

export function clearFieldErrors(form) {
  if (!form) return;
  for (const p of form.querySelectorAll(`p.${ERROR_CLASS}[data-field-error]`)) {
    const id = p.id;
    for (const el of form.querySelectorAll("[aria-describedby]")) removeToken(el, "aria-describedby", id);
    p.remove();
  }
  for (const el of form.querySelectorAll('[aria-invalid="true"]')) el.removeAttribute("aria-invalid");
}

/** `details` = { champ: message } (422). Renvoie les entrees sans champ correspondant, a afficher en message global. */
export function showFieldErrors(form, details, { focus = true } = {}) {
  clearFieldErrors(form);
  const unmatched = {};
  if (!form || !details || typeof details !== "object") return unmatched;

  let first = null;
  for (const [field, message] of Object.entries(details)) {
    const controls = controlsFor(form, field);
    if (controls.length === 0) {
      unmatched[field] = message;
      continue;
    }
    const id = errorIdFor(field);
    const p = document.createElement("p");
    p.id = id;
    p.className = ERROR_CLASS;
    p.dataset.fieldError = "";
    p.textContent = String(message);

    for (const control of controls) {
      control.setAttribute("aria-invalid", "true");
      addToken(control, "aria-describedby", id);
    }
    const last = controls[controls.length - 1];
    (last.closest("label") ?? last).after(p);
    first ??= controls[0];
  }
  if (focus && first) first.focus();
  return unmatched;
}

function baseMessage(err) {
  switch (err.type) {
    case "network":
      return "Impossible de joindre le serveur. Vérifiez votre connexion internet puis réessayez.";
    case "timeout":
      return "Le serveur met trop de temps à répondre. Réessayez dans quelques instants.";
    case "abort":
      return "La requête a été annulée.";
    case "parse":
      return "Le serveur a renvoyé une réponse inattendue. Réessayez plus tard.";
    default:
      break;
  }
  const { status } = err;
  if (status === 400) return "La requête est invalide.";
  if (status === 401) return "Votre session a expiré. Veuillez vous reconnecter.";
  if (status === 403) return "Vous n'avez pas les droits nécessaires pour cette action.";
  if (status === 404) return "L'élément demandé est introuvable.";
  if (status === 409) return "Cette ressource a été modifiée entre-temps. Rechargez la page puis réessayez.";
  if (status === 422) return "Certains champs sont invalides. Corrigez-les puis réessayez.";
  if (status === 429) return "Trop de requêtes. Patientez quelques instants puis réessayez.";
  if (status >= 500) return "Le serveur a rencontré un problème. Réessayez plus tard.";
  return "La requête n'a pas pu aboutir. Réessayez plus tard.";
}

export function messageFor(err) {
  if (!(err instanceof Error) || err.name !== "ApiError") {
    return "Une erreur inattendue est survenue. Réessayez plus tard.";
  }
  const message = baseMessage(err);
  return err.requestId ? `${message} Référence : ${err.requestId}` : message;
}
