// Surcharge locale de la configuration du front.
//
// 1. Copier ce fichier en `assets/js/config.local.js` (ignore par git).
// 2. Adapter les valeurs.
//
// Il est charge en <script> classique (pas un module) avant les modules de la
// page ; `assets/js/core/config.js` lit `window.DAYOUT_CONFIG`.
// Sans ce fichier, les valeurs par defaut de `core/config.js` s'appliquent
// (le navigateur affiche alors un 404 sans consequence dans l'onglet Reseau).
window.DAYOUT_CONFIG = {
  apiBaseUrl: "http://localhost:8000",
};
