// Par le `name` et non instanceof : un api.js charge sous deux URL (cache-busting) donnerait deux classes ApiError.
export function isApiError(err) {
  return err instanceof Error && err.name === "ApiError";
}

function baseMessage(err) {
  switch (err.type) {
    case "network":
      return "Impossible de joindre le serveur. Vérifiez votre connexion internet puis réessayez.";
    case "timeout":
      return "Le serveur met trop de temps à répondre. Réessayez dans quelques instants.";
    case "abort":
      return "La requête a été annulée.";
    case "parse":
      return "Le serveur a renvoyé une réponse inattendue. Réessayez plus tard.";
    default:
      break;
  }
  const { status } = err;
  if (status === 400) return "La requête est invalide.";
  if (status === 401) return "Votre session a expiré. Veuillez vous reconnecter.";
  if (status === 403) return "Vous n'avez pas les droits nécessaires pour cette action.";
  if (status === 404) return "L'élément demandé est introuvable.";
  if (status === 409) return "Cette ressource a été modifiée entre-temps. Rechargez la page puis réessayez.";
  if (status === 422) return "Certains champs sont invalides. Corrigez-les puis réessayez.";
  if (status === 429) return "Trop de requêtes. Patientez quelques instants puis réessayez.";
  if (status >= 500) return "Le serveur a rencontré un problème. Réessayez plus tard.";
  return "La requête n'a pas pu aboutir. Réessayez plus tard.";
}

/** Message utilisateur pour toute erreur, suivi de la reference de requete si l'API en a fourni une. */
export function messageFor(err) {
  if (!isApiError(err)) {
    return "Une erreur inattendue est survenue. Réessayez plus tard.";
  }
  const message = baseMessage(err);
  return err.requestId ? `${message} Référence : ${err.requestId}` : message;
}
