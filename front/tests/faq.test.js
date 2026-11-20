import assert from "node:assert/strict";
import { describe, it } from "node:test";

globalThis.window = /** @type {Window & typeof globalThis} */ ({});
const { FaqController } = await import("../src/controller/FaqController.js");
const { fetchFaq } = await import("../src/model/services/faqService.js");
const { toFaqEntry, toFaqDto } = await import("../src/model/mappers/faqMapper.js");
const { searchFaq } = await import("../src/model/domain/faqSearch.js");
delete globalThis.window;

const dto = { id: 3, question: "Qui peut s'inscrire ?", answer: "Les étudiants.", sort_order: 1, active: true };
const entry = { id: 3, question: "Qui peut s'inscrire ?", answer: "Les étudiants.", sortOrder: 1, active: true };
const entries = [entry];

function page(items, { offset = 0, has_more = false, total = items.length } = {}) {
  return { items, limit: 100, offset, has_more, total };
}

function viewStub() {
  return {
    isReady: true, state: "idle", entries: null, search: null, busy: false, error: "",
    retry: () => {}, query: (/** @type {string} */ _) => {},
    bind({ onRetry, onQuery }) { this.retry = onRetry; this.query = onQuery; },
    showLoading() { this.state = "loading"; this.busy = true; this.error = ""; },
    endLoading() { this.busy = false; },
    showEntries(value, search) { this.entries = value; this.search = search; this.state = "success"; },
    showError(message) { this.error = message; this.state = "error"; },
  };
}

describe("FAQ : mapper", () => {
  it("convertit le JSON de l'API en camelCase et inversement", () => {
    assert.deepEqual(toFaqEntry(dto), entry);
    assert.deepEqual(toFaqDto(entry), dto);
  });

  it("garde le texte brut, sans l'interpreter", () => {
    const raw = { ...dto, question: "<img src=x onerror=alert(1)>", answer: "<b>Texte</b> & réponse" };
    assert.equal(toFaqEntry(raw).question, raw.question);
    assert.equal(toFaqEntry(raw).answer, raw.answer);
  });
});

describe("FAQ : filtre instantane", () => {
  const faq = [
    { id: 1, question: "Comment créer un compte ?", answer: "Avec ton e-mail étudiant." },
    { id: 2, question: "Qu'est-ce que le score de fiabilité ?", answer: "Un indicateur de présence." },
    { id: 3, question: "Comment fonctionne le QR code ?", answer: "Il valide ta PRÉSENCE le jour J." },
  ];

  it("renvoie tout, dans l'ordre, sans saisie", () => {
    assert.deepEqual(searchFaq(faq, ""), faq);
    assert.deepEqual(searchFaq(faq, "   "), faq);
    assert.deepEqual(searchFaq(faq), faq);
  });

  it("cherche dans la question et la reponse, sans casse ni accents", () => {
    assert.deepEqual(searchFaq(faq, "FIABILITE").map(e => e.id), [2]);
    assert.deepEqual(searchFaq(faq, "presence").map(e => e.id), [2, 3]);
    assert.deepEqual(searchFaq(faq, "  qr ").map(e => e.id), [3]);
  });

  it("traite les caracteres speciaux comme du texte", () => {
    assert.deepEqual(searchFaq(faq, "?").map(e => e.id), [1, 2, 3]);
    assert.deepEqual(searchFaq(faq, ".*"), []);
  });
});

describe("FAQ : API", () => {
  it("charge la route publique avec limit/offset et convertit les questions", async t => {
    t.mock.method(globalThis, "fetch", async (url, options) => {
      const query = new URL(url).searchParams;
      assert.equal(new URL(url).pathname, "/faq");
      assert.equal(query.get("limit"), "100");
      assert.equal(query.get("offset"), "0");
      assert.equal(options.method, "GET");
      return Response.json({ data: page([dto]) });
    });
    assert.deepEqual(await fetchFaq(), entries);
  });

  it("accepte une liste vide mais refuse une reponse sans liste ou une pagination sans fin", async t => {
    for (const data of [page([]), null, {}, [], page([], { has_more: true })]) {
      const mock = t.mock.method(globalThis, "fetch", async () => Response.json({ data }));
      if (data?.items?.length === 0 && data.has_more === false) assert.deepEqual(await fetchFaq(), []);
      else await assert.rejects(fetchFaq(), TypeError);
      mock.mock.restore();
    }
  });

  it("charge toutes les pages dans l'ordre", async t => {
    const first = Array.from({ length: 100 }, (_, i) => ({ ...dto, id: i + 1 }));
    const last = [{ ...dto, id: 101, question: "Derniere" }];
    const offsets = [];
    t.mock.method(globalThis, "fetch", async url => {
      const offset = Number(new URL(url).searchParams.get("offset"));
      offsets.push(offset);
      return Response.json({ data: offset === 0
        ? page(first, { has_more: true, total: 101 })
        : page(last, { offset: 100, total: 101 }) });
    });
    assert.deepEqual((await fetchFaq()).map(e => e.id), [...first, ...last].map(e => e.id));
    assert.deepEqual(offsets, [0, 100]);
  });

  it("propage une erreur HTTP sans la confondre avec une liste vide", async t => {
    t.mock.method(globalThis, "fetch", async () => Response.json({ error: { message: "Unavailable" } }, { status: 503 }));
    await assert.rejects(fetchFaq(), { status: 503 });
  });
});

describe("FAQ : controleur", () => {
  it("affiche les donnees et termine le chargement, y compris pour une liste vide", async () => {
    for (const data of [entries, []]) {
      const view = viewStub();
      await new FaqController({ view, loadFaq: async () => data }).start();
      assert.deepEqual(view.entries, data);
      assert.deepEqual(view.search, { query: "", total: data.length });
      assert.equal(view.state, "success");
      assert.equal(view.busy, false);
    }
  });

  it("filtre les questions chargees a chaque saisie, sans recharger", async () => {
    const view = viewStub();
    const faq = [entry, { ...entry, id: 4, question: "Comment signaler ?", answer: "Bouton Signaler." }];
    let calls = 0;
    await new FaqController({ view, loadFaq: async () => { calls++; return faq; } }).start();
    view.query("SIGNALER");
    assert.deepEqual(view.entries.map(e => e.id), [4]);
    assert.deepEqual(view.search, { query: "SIGNALER", total: 2 });
    view.query("");
    assert.deepEqual(view.entries, faq);
    assert.equal(calls, 1);
  });

  it("garde la saisie faite pendant le chargement", async () => {
    const view = viewStub();
    /** @type {(value: typeof entries) => void} */
    let resolve = () => {};
    const controller = new FaqController({ view, loadFaq: () => new Promise(done => { resolve = done; }) });
    const loading = controller.start();
    view.query("aucun");
    assert.equal(view.entries, null);
    resolve(entries);
    await loading;
    assert.deepEqual(view.entries, []);
    assert.deepEqual(view.search, { query: "aucun", total: 1 });
  });

  it("permet de reessayer apres une panne", async () => {
    const view = viewStub();
    let calls = 0;
    await new FaqController({ view, loadFaq: async () => {
      if (++calls === 1) throw new Error("offline");
      return entries;
    } }).start();
    assert.equal(view.state, "error");
    assert.ok(view.error);
    assert.equal(view.busy, false);
    await view.retry();
    assert.equal(view.state, "success");
    assert.equal(view.error, "");
    assert.deepEqual(view.entries, entries);
  });

  it("annule le chargement au depart de la page et ignore une reponse tardive", async () => {
    const view = viewStub();
    const leave = new AbortController();
    /** @type {(value: typeof entries) => void} */
    let resolve = () => {};
    const result = new Promise(done => { resolve = done; });
    let calls = 0;
    const controller = new FaqController({ view, signal: leave.signal, loadFaq: ({ signal }) => {
      calls++;
      assert.equal(signal, leave.signal);
      return result;
    } });
    const loading = controller.start();
    await controller.load();
    assert.equal(calls, 1);
    leave.abort();
    resolve(entries);
    await loading;
    assert.equal(view.entries, null);
    assert.equal(view.error, "");
    await controller.load();
    assert.equal(calls, 1);
  });
});
