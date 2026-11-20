// Plafonds des parametres de l'API, miroir du back : un seul endroit a modifier s'ils changent.
// back : App\Model\Dao\PageRequest (MAX_LIMIT, MAX_OFFSET) et la regle `q` des *FindQuery (string|max:100).

/** `limit` maximum d'une liste paginee (GET /events, /faq, /creators). */
export const MAX_PAGE_LIMIT = 100;

/** `offset` maximum d'une liste paginee. */
export const MAX_PAGE_OFFSET = 10000;

/** Longueur maximale du mot-cle `q`. */
export const MAX_KEYWORD_LENGTH = 100;
