<!-- phil-dev source-sha256=54328fa157dcb1646dbe8b28101c99db33050087a1c51eafdc8ca3c144658021 -->
# JavaScript

Le contrôleur Stimulus du bundle, `phil--map-bundle--map`, se place sur l'élément de la carte à côté du contrôleur Leaflet
de Symfony UX Map, que `phil_map()` nomme après lui. Il se connecte en premier et écoute les événements que Symfony UX
Map émet sur l'élément : il décore la carte, il n'en fait pas une.

| Événement | Ce que fait le contrôleur |
|---|---|
| `ux:map:pre-connect` | Pour un plan d'étage, donne à Leaflet le système de référence plat et maintient la carte dans le plan. Pour une archive PMTiles, retire la couche de tuiles et la garde comme repli. |
| `ux:map:rectangle:before-create` | Donne à un kiosque la classe de son état, et à une zone du quartier la classe `phil-map-area`. |
| `ux:map:connect` | Dessine le plan — son image comme superposition, ou ses tuiles — sous les kiosques et ajuste la carte à lui ; lit l'archive avec `pmtiles` ou `protomaps-leaflet`, ou remet les tuiles de repli si le paquet ne se charge pas. |

Il lit ce que le serveur décrit sous `extra.phil` de la carte — `plan` et `pmtiles` —, le vérifie, et ignore ce qui n'est
pas complet. Il ajoute la classe `hasScript` à l'élément en se connectant : sans JavaScript, l'élément reste caché et la
liste en dessous dit ce que la carte montre. Il fixe `data-plan-drawn` (`image` ou `tiles`) et `data-tiles` (`pmtiles`
ou `fallback`) lorsqu'il a fini.

## La feuille de style

`phil-map/map.css` dimensionne la carte, la cache sans JavaScript, met en forme le titre de la liste, et colore les zones
et les kiosques avec les jetons de la feuille de style de PHIL UX — `--phil-color-success-*` pour les kiosques libres,
`--phil-color-danger-*` pour les pris, `--phil-color-warning-*` pour les fermés, `--phil-color-border` en pointillé pour
les inconnus —, avec des couleurs simples lorsqu'elle n'est pas chargée.

## Les modules

| Module | Rôle |
|---|---|
| `assets/src/map_controller.js` | Le contrôleur. |
| `assets/src/lib/plan.js` | Ce qu'il décide sans Leaflet : la classe d'une forme, le plan et l'archive lus dans `extra.phil`. |
