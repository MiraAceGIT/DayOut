import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { formNavigation, linkNavigation, withParam } from "../src/app/navigation.js";

const here = { origin: "https://dayout.example", pathname: "/events", search: "" };
const click = (overrides = {}) => ({ button: 0, defaultPrevented: false, ...overrides });
const link = (href, overrides = {}) => ({ href, target: "", download: false, ...overrides });

describe("linkNavigation : clics pris en charge par le routeur", () => {
  it("prend un lien vers une page de l'app", () => {
    assert.equal(linkNavigation(click(), link("https://dayout.example/faq"), here)?.href, "https://dayout.example/faq");
  });

  it("laisse au navigateur les ouvertures dans un nouvel onglet et les clics deja traites", () => {
    for (const modifier of ["ctrlKey", "metaKey", "shiftKey", "altKey"]) {
      assert.equal(linkNavigation(click({ [modifier]: true }), link("https://dayout.example/faq"), here), null, modifier);
    }
    assert.equal(linkNavigation(click({ button: 1 }), link("https://dayout.example/faq"), here), null);
    assert.equal(linkNavigation(click({ defaultPrevented: true }), link("https://dayout.example/faq"), here), null);
    assert.equal(linkNavigation(click(), link("https://dayout.example/faq", { target: "_blank" }), here), null);
    assert.equal(linkNavigation(click(), link("https://dayout.example/faq", { download: true }), here), null);
  });

  it("laisse au navigateur les autres sites, les fichiers et les ancres de la page affichee", () => {
    assert.equal(linkNavigation(click(), link("https://openstreetmap.org/copyright"), here), null);
    assert.equal(linkNavigation(click(), link("https://dayout.example/assets/img/logo.svg"), here), null);
    assert.equal(linkNavigation(click(), link("https://dayout.example/events#list"), here), null);
  });

  it("prend une ancre vers une autre page", () => {
    assert.equal(linkNavigation(click(), link("https://dayout.example/event?id=2#map"), here)?.pathname, "/event");
  });
});

describe("formNavigation : recherche du header hors de la carte", () => {
  const form = { method: "get", action: "https://dayout.example/", target: "" };

  it("transforme les champs en query string de la page cible", () => {
    const url = formNavigation(form, [["q", "jazz & co"]], here);
    assert.equal(url?.pathname, "/");
    assert.equal(url?.searchParams.get("q"), "jazz & co");
  });

  it("laisse au navigateur un POST, une autre origine ou une cible", () => {
    assert.equal(formNavigation({ ...form, method: "post" }, [], here), null);
    assert.equal(formNavigation({ ...form, action: "https://ailleurs.example/" }, [], here), null);
    assert.equal(formNavigation({ ...form, target: "_blank" }, [], here), null);
  });
});

describe("withParam", () => {
  it("remplace ou retire un parametre en gardant les autres", () => {
    assert.equal(withParam("https://d.example/?q=a&x=1", "q", "b").href, "https://d.example/?q=b&x=1");
    assert.equal(withParam("https://d.example/?q=a&x=1", "q", "  ").href, "https://d.example/?x=1");
  });
});
