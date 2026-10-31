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

/** Renvoie les erreurs sans champ correspondant, a afficher en message global. */
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
