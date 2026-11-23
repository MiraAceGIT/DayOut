# 0001 — Filtre géographique de `GET /events` par Haversine SQL

- **Date** : 2026-11-23
- **Statut** : proposée
- **Décidé via** : /board (`.claude/board/2026-11-23-g4b-51-map-haversine/`)

## Contexte

Le CdC D1 impose une carte interactive qui affiche les événements autour d'un point choisi, avec rayon réglable 1–20 km. L'implémentation couvre à la fois le backend (nouveaux params `lat`, `lng`, `radius` sur `GET /events` filtrés par Haversine) et le frontend (reload sur changement de zone/rayon, mini-carte sur la page détail). Trois choix structurants méritent d'être tracés :

1. **Où vit le filtre géo ?**
2. **Comment réagit l'API à des paramètres géographiques partiels ?**
3. **Quelle source de vérité quand le front filtre aussi en local ?**

## Options étudiées

1. **Filtre géo serveur (SQL Haversine)** + exposition de `lat/lng/radius` dans la query string. Server filters authoritatively, client ne reçoit que la bonne page. **Choisi.** (+) scalable, (+) respecte le ticket « fetch() à chaque changement de zone/rayon », (−) table scan sans index spatial à l'échelle.
2. **Filtre géo client uniquement** : charger tous les events à venir, filtrer en JS. (+) simple, (−) contredit le ticket, ne scale pas, impossible de paginer intelligemment.
3. **MariaDB spatial / PostGIS** : index R-Tree, `ST_Distance_Sphere`. (+) rapide à l'échelle, (−) hors stack vanilla assumée, (−) migration lourde pour un gain invisible à 10 events.
4. **Co-présence `lat/lng/radius`** vs. ignorer silencieusement un paramètre orphelin. (+) co-présence = fail loud, OpenAPI explicite ; (−) léger zèle REST. **Choisi : co-présence, 422 sinon.**
5. **Supprimer `withinRadius` client** (serveur fait foi, pas de duplication) vs. **Garder le filtre client** comme défense UX pendant le debounce du curseur. **Choisi : garder, renommer en `withinLoadedRadius` + commentaire « SQL fait foi ».** Responsabilité = filtrer le dataset chargé contre les critères UI.

## Décision

Filtre géographique **au serveur**, en SQL Haversine, isolé dans `Event::findManyPublished()`. Paramètres `lat`, `lng`, `radius` obligatoires ensemble (`422 VALIDATION_ERROR` sinon), bornés `[-90, 90]` / `[-180, 180]` / `[1, 20] km` (constantes `EventFindQuery::MIN_RADIUS_KM` / `MAX_RADIUS_KM` miroirées en `front/src/model/mappers/event/eventRequestMapper.js`). **Source de vérité = SQL serveur** ; le filtre JS `withinLoadedRadius` reste une défense UX pendant le debounce 220 ms du curseur de rayon, pas un filtre de contrat.

Formule Haversine inline aujourd'hui (constante nommée après extraction de lisibilité) ; **pas d'index spatial**, **pas de bounding-box prefilter** à ce stade — le seed est de 10 événements, invisible.

## Conséquences

### Positives
- API RESTful respectant le CdC D1, contrat OpenAPI explicite.
- Isolation : passer à PostGIS / `ST_Distance_Sphere` / Elasticsearch = réécrire la clause `Event::findManyPublished()`, zéro impact sur Service, Controller, DTO, front.
- Co-présence + bornes serrées : surface d'injection et de valeurs anormales minimale, bindings `?` partout.
- Événements sans coordonnées proprement exclus (impossibles à placer sur la carte).

### Négatives / dette acceptée
- **Pas d'index spatial** : la clause Haversine impose un table scan, invisible à 10 seeds, inacceptable à > 1000 events ou trafic public. Mitigation prévue : bounding-box prefilter `latitude BETWEEN ? AND ? AND longitude BETWEEN ? AND ?` + index `(latitude, longitude)`, à faire avant ouverture publique.
- **Pas de rate limiting** sur `/events` public. Vecteur DoS applicatif à régler en parallèle (ticket séparé).
- **Duplication de la formule rayon** : serveur en SQL, client en JS. Les deux utilisent exactement la même constante `6371 km`. Divergence possible si quelqu'un modifie un côté. Atténué par le commentaire du filtre JS et par le présent ADR.
- **Événement non géocodé = invisible dans une recherche géo**, sans alerte UI côté organisateur. À remonter dans la future UI de création d'event.

### Ce qui nous ferait revenir dessus
- Charge réelle > 100 req/s sur `/events` → bounding-box devient urgent.
- Passage à une BDD spatiale (PostGIS / MySQL spatial) → l'ADR tombe au profit de `ST_Distance_Sphere`.
- Besoin de rayons < 1 km (bars, campus) → remonter `MIN_RADIUS_KM`.
- Divergence observée entre résultats SQL et affichage client → signe que la duplication a dérivé, retirer le filtre JS.
