// Securite : enfants string -> noeuds texte (jamais de HTML) ; innerHTML/outerHTML/srcdoc, handlers `on*`
// en string et URL non http(s) dans href/src... (javascript:, data:) sont refuses.

const FORBIDDEN_PROPS = new Set(["innerhtml", "outerhtml", "srcdoc"]);
const URL_ATTRS = new Set(["href", "src", "action", "formaction", "poster", "cite", "xlink:href"]);
const SAFE_PROTOCOLS = new Set(["http:", "https:"]);

/** new URL() normalise comme le navigateur : `java\tscript:` ou ` javascript:` sont donc bien bloques. */
export function isSafeUrl(value) {
  try {
    return SAFE_PROTOCOLS.has(new URL(String(value), document.baseURI).protocol);
  } catch {
    return false;
  }
}

function setProp(el, name, value) {
  if (value === null || value === undefined || value === false) return;
  const lower = name.toLowerCase();

  if (FORBIDDEN_PROPS.has(lower)) {
    console.warn(`h(): prop "${name}" refusee (jamais de HTML brut).`);
    return;
  }
  if (name === "class" || name === "className") {
    el.className = Array.isArray(value) ? value.filter(Boolean).join(" ") : String(value);
    return;
  }
  if (name === "dataset" && typeof value === "object") {
    for (const [k, v] of Object.entries(value)) {
      if (v !== null && v !== undefined) el.dataset[k] = String(v);
    }
    return;
  }
  if (lower.startsWith("on")) {
    if (typeof value === "function") {
      el.addEventListener(lower.slice(2), value);
    } else {
      console.warn(`h(): handler "${name}" ignore (fonction attendue).`);
    }
    return;
  }
  if (URL_ATTRS.has(lower) && !isSafeUrl(value)) {
    console.warn(`h(): URL refusee pour "${name}".`);
    return;
  }
  el.setAttribute(name, value === true ? "" : String(value));
}

function appendChildren(el, children) {
  for (const child of children) {
    if (child === null || child === undefined || child === false || child === true) continue;
    if (Array.isArray(child)) {
      appendChildren(el, child);
    } else if (child instanceof Node) {
      el.append(child);
    } else {
      el.append(String(child));
    }
  }
}

/** props : `class` (string|tableau), `dataset` (objet), `on<Event>` (fonction) ; true -> attribut vide, false/null -> ignore. */
export function h(tag, props = {}, ...children) {
  const el = document.createElement(tag);
  for (const [name, value] of Object.entries(props ?? {})) setProp(el, name, value);
  appendChildren(el, children);
  return el;
}
