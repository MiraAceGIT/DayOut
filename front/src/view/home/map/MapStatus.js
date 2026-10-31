// Cles = codes de rejet de core/geolocation.js.
const LOCATE_ERRORS = {
  denied: "Tu as refusé l'accès à ta position. Clique sur la carte pour choisir un point.",
  unsupported: "Ton navigateur ne permet pas de te localiser. Clique sur la carte pour choisir un point.",
  timeout: "Ta position met trop de temps à arriver. Réessaie ou clique sur la carte.",
  unavailable: "Ta position est introuvable pour le moment. Clique sur la carte pour choisir un point.",
};

/** Messages de la carte (#home-map-status, role=status) : seul endroit qui en connait les textes. */
export class MapStatus {
  constructor(statusEl) {
    this.statusEl = statusEl;
  }

  show(message) {
    if (this.statusEl) this.statusEl.textContent = message;
  }

  clear() {
    this.show("");
  }

  unavailable() {
    this.show("La carte n'a pas pu être chargée. La liste des événements reste disponible.");
  }

  pickUnavailable() {
    this.show("La carte est indisponible : le point ne peut pas être changé pour le moment.");
  }

  pickHint() {
    this.show("Clique sur la carte pour déplacer le point, ou appuie sur Entrée pour choisir son centre.");
  }

  locating() {
    this.show("Recherche de ta position…");
  }

  located() {
    this.show("Point placé sur ta position.");
  }

  /** `from` : "map" (clic, Entree) ou "device" (localisation). */
  outOfArea(areaName, from) {
    this.show(from === "device"
      ? `Tu sembles être hors ${areaName} : DayOut ne couvre que cette région. Clique sur la carte pour choisir un point.`
      : `DayOut ne couvre que l'${areaName} : choisis un point dans la région.`);
  }

  locateFailed(code) {
    this.show(LOCATE_ERRORS[code] ?? LOCATE_ERRORS.unavailable);
  }
}
