import { toLegalPage } from "../model/mappers/legalMapper.js";

function renderSimpleMarkdown(md) {
  return md
    .replace(/^## (.*$)/gim, "<h2>$1</h2>")
    .replace(/\*\*(.*?)\*\*/gim, "<strong>$1</strong>")
    .replace(/^\* (.*$)/gim, "<li>$1</li>")
    .replace(/(<li>.*<\/li>)/gims, "<ul>$1</ul>")
    .replace(/\n\n/g, "</p><p>")
    .replace(/^([^<].*)$/gim, "<p>$1</p>");
}

export class LegalController {
  constructor({ baseUrl = "http://localhost:8000" } = {}) {
    this.baseUrl = baseUrl;
  }

  async init() {
    const container = document.querySelector("[data-legal-type]");
    if (!container) return;

    const type = container.dataset.legalType;
    const titleEl = document.getElementById("legal-title");
    const dateEl = document.getElementById("legal-updated-at");
    const bodyEl = document.getElementById("legal-body");

    try {
      const response = await fetch(`${this.baseUrl}/legal/${encodeURIComponent(type)}`);
      if (!response.ok) {
        throw new Error(`Erreur HTTP ${response.status}`);
      }

      const payload = await response.json();
      const rawDto = payload.data ?? payload;
      const page = toLegalPage(rawDto);

      if (titleEl && page.title) titleEl.textContent = page.title;
      if (dateEl && page.updatedAtLabel) dateEl.textContent = page.updatedAtLabel;
      if (bodyEl) bodyEl.innerHTML = renderSimpleMarkdown(page.bodyMd);
    } catch (err) {
      console.error(err);
      if (bodyEl) {
        bodyEl.innerHTML = "<p>Impossible de charger le contenu légal pour le moment.</p>";
      }
    }
  }
}