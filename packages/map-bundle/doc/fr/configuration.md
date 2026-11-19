<!-- phil-dev source-sha256=1a841dfedaec8a4211f17be7eb6f6de916d0577667f93703cac6e6fa4398e26b -->
# Configuration

La configuration du bundle se trouve sous `phil_map`. Chaque option a une valeur par défaut :

```yaml
# config/packages/phil_map.yaml
phil_map:
    tiles:
        source: proxy                 # proxy, direct, pmtiles or none
        url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        max_zoom: 19
        allow_localhost: false
        anonymous: true
        proxy:
            cache: cache.app
            ttl: 604800
            user_agent: 'phil/map-bundle tile proxy'
            timeout: 5.0
            max_bytes: 524288
        pmtiles:
            url: ~                    # /maps/world.pmtiles
            kind: raster              # raster or vector
            attribution: ~
            max_zoom: 15
            flavor: light
    neighbourhood:
        cell_metres: 1000
        minimum: 3
    booths:
        availability: ~
    image_tiles:
        max_pixels: 40000000
        tile_size: 256
    kit_subdirectory: ~
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `tiles.source` | `proxy` | Comment le navigateur obtient les tuiles : par le site (`proxy`), de la source (`direct`), d'une archive PMTiles (`pmtiles`), ou aucune. Voir [Tuiles](tiles.md). |
| `tiles.url` | les tuiles d'OpenStreetMap | Le gabarit de la source de tuiles, avec `{z}`, `{x}` et `{y}` ; lu par `phil/url` une fois chaque substitut remplacé. |
| `tiles.attribution` | celle d'OpenStreetMap | L'attribution qu'exige la licence des tuiles, en HTML, affichée sur la carte. |
| `tiles.max_zoom` | `19` | Le zoom le plus profond de la source, de 0 à 22. |
| `tiles.allow_localhost` | `false` | Si `localhost` est un hôte de la source et de l'archive, pour le développement et les tests. |
| `tiles.anonymous` | `true` | Si les tuiles directes sont des requêtes anonymes entre origines, sans témoins ; la source répond alors avec des en-têtes CORS. |
| `tiles.proxy.cache` | `cache.app` | Le pool de cache qui conserve les tuiles passées par le mandataire. |
| `tiles.proxy.ttl` | `604800` | Combien de temps une tuile est conservée, et mise en cache par les navigateurs, en secondes. |
| `tiles.proxy.user_agent` | `phil/map-bundle tile proxy` | L'agent utilisateur sous lequel le mandataire se nomme ; les politiques de tuiles en demandent un qui nomme le site et un contact. |
| `tiles.proxy.timeout` | `5.0` | Les secondes dont la source dispose pour répondre. |
| `tiles.proxy.max_bytes` | `524288` | La plus grande tuile acceptée, en octets. |
| `tiles.pmtiles.url` | `null` | L'archive, un chemin du site ou une adresse ; obligatoire avec la source `pmtiles`. |
| `tiles.pmtiles.kind` | `raster` | `raster` pour une archive d'images, `vector` pour une archive de tuiles vectorielles. |
| `tiles.pmtiles.attribution` | `null` | L'attribution de l'archive ; celle de la source lorsque la valeur est nulle. |
| `tiles.pmtiles.max_zoom` | `15` | Le zoom le plus profond de l'archive. |
| `tiles.pmtiles.flavor` | `light` | Le style d'une archive vectorielle, une variante de `protomaps-leaflet`. |
| `neighbourhood.cell_metres` | `1000` | Le côté d'une cellule de la grille de vie privée, de 100 m à 50 km. Voir [Vie privée](privacy.md). |
| `neighbourhood.minimum` | `3` | Le moins de positions qu'une cellule contient pour être montrée, de 1 à 1000. |
| `booths.availability` | `null` | Le service, un `BoothAvailability`, qui donne les états des kiosques ; celui de `phil/reservation-bundle` lorsqu'il est activé, sinon aucun. |
| `image_tiles.max_pixels` | `40000000` | La plus grande image que le découpeur décode ; GD occupe environ cinq octets de mémoire par pixel. |
| `image_tiles.tile_size` | `256` | Le côté d'une tuile, de 64 à 1024 pixels. |
| `kit_subdirectory` | `null` | L'endroit où les recettes du kit PHIL UX sont installées ; le sous-répertoire de `ux_toolkit` lorsque la valeur est nulle. |

Un gabarit de tuile qui n'est pas une adresse ou qui manque d'un substitut, et une source PMTiles sans archive,
empêchent la compilation du conteneur.

## Services

| Service | Rôle |
|---|---|
| `Phil\MapBundle\Application\Maps` | Construit les cartes et leurs listes ; public. |
| `Phil\MapBundle\Application\Neighbourhood` | Compte les positions par cellule de la grille ; public. |
| `Phil\MapBundle\Application\Contract\BaseLayer` | Les tuiles que choisit la configuration. |
| `Phil\MapBundle\Application\Contract\BoothAvailability` | Les états des kiosques. |
| `Phil\MapBundle\Application\Contract\ImageTiler` | Le découpeur GD ; public. |
| `Phil\MapBundle\Infrastructure\Http\TileProxyController` | Le mandataire de tuiles, derrière la route `phil_map_tile`. |
