import assert from "node:assert/strict";
import { it } from "node:test";
import { setImmediate } from "node:timers/promises";
import { eventDto } from "./fixtures.js";

globalThis.window = /** @type {Window & typeof globalThis} */ ({});
const { startRouter } = await import("../src/app/router.js");
const { fetchEvent } = await import("../src/model/services/eventService.js");
delete globalThis.window;

it("un changement d'id recharge le bon evenement, par lien et par Precedent/Suivant", async (t) => {
  const documentEvents = new EventTarget();
  const windowEvents = new EventTarget();
  let address = new URL("https://dayout.example/event?id=1");
  const apiPaths = [];
  const displayedIds = [];
  const loads = [];

  class Link {
    constructor(href) { this.href = href; this.target = ""; }
    closest() { return this; }
    hasAttribute() { return false; }
  }
  const browser = {
    document: { addEventListener: documentEvents.addEventListener.bind(documentEvents), title: "" },
    window: { addEventListener: windowEvents.addEventListener.bind(windowEvents), scrollTo() {} },
    location: { get pathname() { return address.pathname; }, get search() { return address.search; },
      get origin() { return address.origin; }, get href() { return address.href; } },
    history: { pushState(_state, _title, url) { address = new URL(url); } },
    DOMParser: class {
      parseFromString() { return { title: "Event", getElementById: () => ({ className: "event-page", childNodes: [] }) }; }
    },
    Element: Link, HTMLAnchorElement: Link,
    fetch: async (url) => {
      const path = new URL(String(url)).pathname;
      if (path.startsWith("/events/")) {
        apiPaths.push(path);
        return new Response(JSON.stringify({ data: eventDto({ id: Number(path.split("/").at(-1)) }) }),
          { headers: { "content-type": "application/json" } });
      }
      return new Response("page");
    },
  };
  for (const [name, value] of Object.entries(browser)) {
    const previous = Object.getOwnPropertyDescriptor(globalThis, name);
    Object.defineProperty(globalThis, name, { configurable: true, writable: true, value });
    t.after(() => { if (previous) Object.defineProperty(globalThis, name, previous); else delete globalThis[name]; });
  }

  function click(href) {
    const event = new Event("click", { cancelable: true });
    Object.defineProperties(event, { target: { value: new Link(href) }, button: { value: 0 } });
    documentEvents.dispatchEvent(event);
    return event;
  }
  async function finishNavigation() {
    await setImmediate();
    await Promise.all(loads);
  }

  startRouter({
    routes: [{ path: "/event", file: "pages/event.html", mount: () => {
      // Meme lecture que la page detail : le service recoit l'id de l'URL courante.
      loads.push(fetchEvent(address.searchParams.get("id")).then(event => displayedIds.push(event.id)));
    } }],
    notFound: { file: "pages/not-found.html" }, root: { className: "", replaceChildren() {}, focus() {} }, onChange() {},
  });
  await finishNavigation();
  assert.deepEqual(displayedIds, [1]);

  assert.equal(click("https://dayout.example/event?id=2").defaultPrevented, true);
  await finishNavigation();
  assert.deepEqual(displayedIds, [1, 2]);

  assert.equal(click("https://dayout.example/event?id=3#event-map").defaultPrevented, true);
  await finishNavigation();
  assert.deepEqual(displayedIds, [1, 2, 3]);

  for (const id of [2, 3]) {
    address = new URL(`https://dayout.example/event?id=${id}`);
    windowEvents.dispatchEvent(new Event("popstate"));
    await finishNavigation();
  }
  assert.deepEqual(displayedIds, [1, 2, 3, 2, 3]);
  assert.deepEqual(apiPaths, ["/events/1", "/events/2", "/events/3", "/events/2", "/events/3"]);

  assert.equal(click("https://dayout.example/event?id=3#event-map").defaultPrevented, false);
  address.hash = "event-map";
  windowEvents.dispatchEvent(new Event("popstate"));
  await finishNavigation();
  assert.equal(apiPaths.length, 5, "une ancre seule ne declenche pas de requete API");
});
