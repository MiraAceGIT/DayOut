/**
 * Position de l'appareil, demandee uniquement sur action explicite (regle de confidentialite de la spec).
 * Dans core/ et non shared/ : depend de l'API navigateur. Rejette avec
 * { code: "unsupported" | "denied" | "unavailable" | "timeout" }.
 */
export function currentPosition({ timeout = 10000 } = {}) {
  return new Promise((resolve, reject) => {
    if (!("geolocation" in navigator)) {
      reject({ code: "unsupported" });
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
      (err) => reject({ code: { 1: "denied", 2: "unavailable", 3: "timeout" }[err.code] ?? "unavailable" }),
      { enableHighAccuracy: false, timeout, maximumAge: 60000 },
    );
  });
}
