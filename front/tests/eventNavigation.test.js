import assert from "node:assert/strict";
import { it } from "node:test";
import { setImmediate } from "node:timers/promises";
import { eventDto } from "./fixtures.js";

globalThis.window = /** @type {Window & typeof globalThis} */ ({});
const { startRouter } = await import("../src/app/router.js");
const { fetchEvent } = await import("../src/model/services/eventService.js");
delete globalThis.window;

class Link {
  constructor(href) { this.href = href; this.target = ""; }
  closest() { return this; }
  hasAttribute() { return false; }
}

/**
 * Navigateur minimal autour du routeur : adresse, historique, evenements du document et de window.
 * `apiFetch` repond aux appels de l'API ; les pages HTML sont toutes un <main> vide.
 * @param {import("node:test").TestContext} t
 * @param {string} start
 * @param {(url: URL) => Response | undefined} [apiFetch]
 */
function installBrowser(t, start, apiFetch = () => undefined) {
  const documentEvents = new EventTarget();
  const windowEvents = new EventTarget();
  const browser = { address: new URL(start) };
  const globals = {
    document: { addEventListener: documentEvents.addEventListener.bind(documentEvents), title: "" },
    window: { addEventListener: windowEvents.addEventListener.bind(windowEvents), scrollTo() {} },
    location: { get pathname() { return browser.address.pathname; }, get search() { return browser.address.search; },
      get origin() { return browser.address.origin; }, get href() { return browser.address.href; } },
    history: {
      state: null,
      pushState(_state, _title, url) { browser.address = new URL(url); },
      replaceState(_state, _title, url) { browser.address = new URL(url); },
    },
    DOMParser: class {
      parseFromString() { return { title: "Page", getElementById: () => ({ className: "", childNodes: [] }) }; }
    },
    Element: Link, HTMLAnchorElement: Link,
    fetch: async (url) => apiFetch(new URL(String(url))) ?? new Response("page"),
  };
  for (const [name, value] of Object.entries(globals)) {
    const previous = Object.getOwnPropertyDescriptor(globalThis, name);
    Object.defineProperty(globalThis, name, { configurable: true, writable: true, value });
    t.after(() => { if (previous) Object.defineProperty(globalThis, name, previous); else delete globalThis[name]; });
  }
  return Object.assign(browser, {
    /** @param {string} href */
    click(href) {
      const event = new Event("click", { cancelable: true });
      Object.defineProperties(event, { target: { value: new Link(href) }, button: { value: 0 } });
      documentEvents.dispatchEvent(event);
      return event;
    },
    /** @param {string} href */
    back(href) {
      browser.address = new URL(href);
      windowEvents.dispatchEvent(new Event("popstate"));
    },
    root: /** @type {any} */ ({ className: "", replaceChildren() {}, focus() {} }),
  });
}

it("un changement d'id recharge le bon evenement, par lien et par Precedent/Suivant", async (t) => {
  const apiPaths = [];
  const displayedIds = [];
  const loads = [];
  const browser = installBrowser(t, "https://dayout.example/event?id=1", (url) => {
    if (!url.pathname.startsWith("/events/")) return undefined;
    apiPaths.push(url.pathname);
    return new Response(JSON.stringify({ data: eventDto({ id: Number(url.pathname.split("/").at(-1)) }) }),
      { headers: { "content-type": "application/json" } });
  });
  async function finishNavigation() {
    await setImmediate();
    await Promise.all(loads);
  }

  startRouter({
    routes: [{ path: "/event", file: "pages/event.html", mount: ({ params }) => {
      // Meme lecture que la page detail : le service recoit l'id de l'URL courante.
      loads.push(fetchEvent(params.get("id") ?? "").then(event => displayedIds.push(event.id)));
    } }],
    notFound: { file: "pages/not-found.html" }, root: browser.root, onChange() {},
  });
  await finishNavigation();
  assert.deepEqual(displayedIds, [1]);

  assert.equal(browser.click("https://dayout.example/event?id=2").defaultPrevented, true);
  await finishNavigation();
  assert.deepEqual(displayedIds, [1, 2]);

  assert.equal(browser.click("https://dayout.example/event?id=3#event-map").defaultPrevented, true);
  await finishNavigation();
  assert.deepEqual(displayedIds, [1, 2, 3]);

  for (const id of [2, 3]) {
    browser.back(`https://dayout.example/event?id=${id}`);
    await finishNavigation();
  }
  assert.deepEqual(displayedIds, [1, 2, 3, 2, 3]);
  assert.deepEqual(apiPaths, ["/events/1", "/events/2", "/events/3", "/events/2", "/events/3"]);

  assert.equal(browser.click("https://dayout.example/event?id=3#event-map").defaultPrevented, false);
  browser.back("https://dayout.example/event?id=3#event-map");
  await finishNavigation();
  assert.equal(apiPaths.length, 5, "une ancre seule ne declenche pas de requete API");
});

it("setParam garde le routeur d'accord avec l'adresse : un lien vers l'adresse d'avant reaffiche la page", async (t) => {
  const browser = installBrowser(t, "https://dayout.example/");
  /** @type {URLSearchParams[]} */
  const mounts = [];
  /** @type {(name: string, value: string) => void} */
  let setParam = () => {};
  startRouter({
    routes: [{ path: "/", file: "pages/home.html", mount: (page) => { mounts.push(page.params); setParam = page.setParam; } }],
    notFound: { file: "pages/not-found.html" }, root: browser.root, onChange() {},
  });
  await setImmediate();
  assert.equal(mounts.length, 1);

  // Recherche "jazz" sur la carte : l'adresse change sans recharger la page.
  setParam("q", "jazz");
  assert.equal(browser.address.href, "https://dayout.example/?q=jazz");

  // Lien "Carte" (adresse sans q) : ce n'est plus la page affichee, elle est donc reaffichee sans recherche.
  browser.click("https://dayout.example/");
  await setImmediate();
  assert.equal(mounts.length, 2);
  assert.equal(mounts[1].get("q"), null);

  // Une page quittee ne touche plus l'adresse.
  const staleSetParam = setParam;
  browser.click("https://dayout.example/?q=rock");
  await setImmediate();
  staleSetParam("q", "jazz");
  assert.equal(browser.address.href, "https://dayout.example/?q=rock");
});

it("une erreur au demarrage d'une page est journalisee, pas perdue", async (t) => {
  const browser = installBrowser(t, "https://dayout.example/");
  const logged = t.mock.method(console, "error", () => {});
  startRouter({
    routes: [{ path: "/", file: "pages/home.html", mount: async () => { throw new Error("boom"); } }],
    notFound: { file: "pages/not-found.html" }, root: browser.root, onChange() {},
  });
  await setImmediate();
  await setImmediate();
  assert.equal(logged.mock.callCount(), 1);
  assert.match(String(logged.mock.calls[0].arguments[0]), /pages\/home\.html/);
});
