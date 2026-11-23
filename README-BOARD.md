# Board dev — mode d'emploi

## Installation
1. Copie `CLAUDE.md`, `.claude/` et `docs/adr/` à la racine de ton repo.
2. `chmod +x .claude/hooks/*.sh`
3. Prérequis : `jq`, et dans le `package.json` les scripts `lint`, `typecheck`, `test`.
4. Adapte les sections 🔧 de `CLAUDE.md`.
5. Ajoute `.claude/board/` à ton `.gitignore` si tu ne veux pas versionner les débats (les ADR, eux, se versionnent).

## Qui fait quoi

| Agent | Rôle | Quand |
|---|---|---|
| architecte | couches, dépendances, découpage | board |
| clean-code | lisibilité, simplicité (Head of Common Sense) | board |
| maintenabilite | vision 6 mois / 2 ans (Strategic foresight) | board |
| pragmatique | livrer vite, anti over-engineering (Moneymaker) | board |
| securite | menaces, OWASP, données | board |
| avocat-du-diable | contredit tout par principe | board |
| testeur | TDD : tests avant le code | chaque feature |
| reviewer | relit le diff avant commit | chaque feature |

## Utilisation
- Grosse décision : `/board Est-ce qu'on passe l'auth sur des sessions httpOnly au lieu de JWT en localStorage ?`
- Décision moyenne : `/board --light Pinia ou composables pour l'état du panier ?`
- Au quotidien : "utilise l'agent testeur pour…", puis "passe le diff au reviewer".

## Garde-fous automatiques
- Après chaque édition : Prettier + ESLint sur le fichier (hook `lint-file.sh`).
- Avant de rendre la main : typecheck + tests (hook `quality-gate.sh`).
- En CI : GitLab + quality gate SonarQube bloquant le merge.

## Coût
Un `/board` complet = 6 agents × 2 tours (+ arbitrage éventuel). Ça consomme beaucoup de tokens :
réserve-le aux décisions qui coûteraient cher à défaire. Pour réduire la facture, passe `architecte`
et `avocat-du-diable` de `opus` à `sonnet` dans leur frontmatter.
