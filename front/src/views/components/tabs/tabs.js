/**
 * Onglets ARIA sur un markup existant (role=tab + aria-controls) : activation automatique,
 * fleches gauche/droite, Debut/Fin, tabindex itinerant. `root` sert a trouver les panneaux.
 */
export function bindTabs(tabs, { root = document } = {}) {
  const select = (tab, { focus = false } = {}) => {
    for (const t of tabs) {
      const active = t === tab;
      t.setAttribute("aria-selected", String(active));
      t.tabIndex = active ? 0 : -1;
      const panel = root.querySelector(`#${t.getAttribute("aria-controls")}`);
      if (panel) panel.hidden = !active;
    }
    if (focus) tab.focus();
  };

  for (const tab of tabs) {
    tab.addEventListener("click", () => select(tab));
    tab.addEventListener("keydown", (e) => {
      const i = tabs.indexOf(tab);
      const last = tabs.length - 1;
      const next = { ArrowRight: i === last ? 0 : i + 1, ArrowLeft: i === 0 ? last : i - 1, Home: 0, End: last }[e.key];
      if (next === undefined) return;
      e.preventDefault();
      select(tabs[next], { focus: true });
    });
  }
}
