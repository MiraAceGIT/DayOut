---
name: board
description: Convoque le board technique (architecte, clean-code, maintenabilite, pragmatique, securite, avocat-du-diable) pour une décision importante - architecture, choix de lib, gros refacto, nouvelle feature structurante. Avis indépendants, débat, synthèse, ADR.
disable-model-invocation: true
---
# /board — Board technique

Sujet : $ARGUMENTS

> Coûteux en tokens (6 agents × 2 tours). Réservé aux décisions structurantes.
> Mode léger : si le sujet commence par `--light`, ne convoque que architecte, pragmatique et avocat-du-diable, et saute le Tour 2.

## Étape 0 — Préparer la fiche (toi, session principale)
1. Lis `CLAUDE.md` et les ADR de `docs/adr/` liés au sujet.
2. Explore le code concerné.
3. Si le sujet est flou, pose-moi **au maximum 3 questions** avant de continuer.
4. Crée le dossier `.claude/board/AAAA-MM-JJ-<slug>/` et écris `00-fiche.md` :
   ```
   # Fiche board : <titre>
   ## Question posée
   ## Contexte (projet, existant, ADR liés)
   ## Contraintes (délai, perf, équipe, budget…)
   ## Fichiers concernés (chemins)
   ## Options déjà envisagées (s'il y en a)
   ```

## Tour 1 — Avis indépendants
- Lance **en parallèle, dans un seul message**, les 6 sous-agents :
  `architecte`, `clean-code`, `maintenabilite`, `pragmatique`, `securite`, `avocat-du-diable`.
- Chacun reçoit **exactement la même fiche** (colle le contenu complet de `00-fiche.md`), avec la consigne :
  "Réponds seul, sans chercher le consensus, avec ton format de réponse Tour 1."
- Enregistre chaque réponse dans `01-<agent>.md`.

## Tour 2 — Débat
- Lance de nouveau les 6 agents en parallèle. Chacun reçoit la fiche + **toutes** les réponses du Tour 1, avec la consigne :
  ```
  Voici les avis des autres membres du board.
  Réponds en 3 parties :
  1. Je maintiens : …
  2. Je concède (et pourquoi) : …
  3. Je conteste (cite l'agent et l'argument) : …
  Termine par ta recommandation finale en une phrase.
  ```
- Enregistre dans `02-<agent>.md`.
- S'il reste un désaccord majeur entre 2 agents sur un point critique, fais **un seul** tour d'arbitrage entre ces deux-là (pas plus).

## Synthèse (toi)
Écris `03-synthese.md` puis présente-la-moi :
```
# Décision du board : <titre>
## Verdict
Option retenue + justification en 3 lignes.
## Consensus
## Désaccords restants (et qui les porte)
## Risques acceptés
## Mesures obligatoires (sécu, tests…)
## Plan d'action
1. PR 1 : …  (effort S/M/L)
2. PR 2 : …
## Ce qui nous ferait revenir sur cette décision
```

## ADR
Rédige `docs/adr/NNNN-<slug>.md` à partir de `docs/adr/0000-template.md` (NNNN = numéro suivant).
**Ne commite rien** : je relis et je valide la décision moi-même.

## Règles
- Tu es le modérateur, pas un membre : tu ne fais pas pencher la balance.
- Ne lisse pas les désaccords. Un désaccord bien documenté vaut mieux qu'un faux consensus.
- Si tout le monde est d'accord dès le Tour 1, dis-le, et demande explicitement à l'avocat du diable d'attaquer ce consensus au Tour 2.
