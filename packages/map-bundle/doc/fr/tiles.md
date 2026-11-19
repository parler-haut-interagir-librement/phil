<!-- phil-dev source-sha256=0e902755a2c700238615d1463baec1deb2936211d4bfce81facd1168513f8418 -->
# Tuiles

Une carte géographique se dessine sur des tuiles, des images de 256 pixels que le navigateur va chercher pour chaque zoom
et chaque position. Celui qui les sert voit quelle partie du monde le visiteur regarde. `tiles.source` décide qui c'est.

| Source | Le navigateur demande à | Le serveur de tuiles voit |
|---|---|---|
| `proxy` (par défaut) | le site | le site, sous son agent utilisateur ; jamais l'adresse, les témoins ni le référent du visiteur |
| `direct` | le serveur de tuiles | l'adresse du visiteur ; pas de référent, et pas de témoins lorsque `anonymous` est activé |
| `pmtiles` | le site, pour des plages d'octets d'une seule archive | personne : il n'y a pas de serveur de tuiles |
| `none` | rien | personne : les marqueurs et les formes seulement |

## Le mandataire

`ProxiedTiles` dirige Leaflet vers la route `phil_map_tile`, `/_map/tiles/{z}/{x}/{y}` avec le préfixe de la recette.
Le contrôleur prend la tuile au gabarit de `tiles.url` avec le client HTTP de Symfony et la conserve dans le pool de
cache de `tiles.proxy.cache` pendant `tiles.proxy.ttl` secondes ; les navigateurs peuvent la garder aussi longtemps.

Il ne prend que ce que contient la grille de la source — au zoom z, x et y de 0 à 2^z − 1, et z jusqu'à
`tiles.max_zoom` ; tout le reste reçoit la réponse 404 sans requête. La source dispose de `tiles.proxy.timeout`
secondes ; sa réponse est une image — PNG, JPEG ou WebP — d'au plus `tiles.proxy.max_bytes`, vérifié pendant le
téléchargement, sans redirections. Tout le reste reçoit la réponse 502 et n'est pas conservé, de sorte que la requête
suivante redemande. Le mandataire envoie son propre agent utilisateur et son propre en-tête `Accept` et rien de la
requête du visiteur.

Le gabarit par défaut est celui des tuiles standard d'OpenStreetMap. Leur politique d'utilisation demande un agent
utilisateur qui nomme le site et une mise en cache, et interdit le trafic lourd : elles conviennent au développement et
à un usage léger. Un site qui a un vrai trafic nomme un fournisseur de tuiles avec lequel il a une entente, ou sert une
archive PMTiles.

```yaml
phil_map:
    tiles:
        url: 'https://tiles.example.org/{z}/{x}/{y}.png'
        attribution: '© Example tiles, © OpenStreetMap contributors'
        proxy:
            user_agent: 'example.org maps (maps@example.org)'
            cache: cache.tiles
```

## Les tuiles directes

`DirectTiles` donne à Leaflet le gabarit lui-même, avec `referrerPolicy: no-referrer` et, sauf si `tiles.anonymous` est
désactivé, `crossOrigin: anonymous` : le navigateur n'envoie aucun témoin, et le serveur de tuiles doit répondre avec des
en-têtes CORS.

## Les archives PMTiles

Une archive PMTiles contient toutes les tuiles d'une région dans un seul fichier, lu par requêtes de plage : le site la
sert comme n'importe quel fichier statique, et aucun serveur de tuiles n'existe. `PmtilesLayer` est l'adaptateur du
bundle pour elle :

```yaml
phil_map:
    tiles:
        source: pmtiles
        pmtiles:
            url: /maps/region.pmtiles
            kind: vector        # raster: images; vector: vector tiles drawn in the browser
            attribution: '© OpenStreetMap contributors'
            max_zoom: 15
            flavor: light
```

L'archive est lue dans le navigateur par le paquet `pmtiles` pour une archive raster et par `protomaps-leaflet` pour une
archive vectorielle ; les deux sont facultatifs, ajoutés à l'import map par l'application :

```sh
php bin/console importmap:require pmtiles
php bin/console importmap:require protomaps-leaflet
```

Lorsque l'import map d'AssetMapper n'a pas le paquet, `PmtilesLayer` le dit dans le journal et la carte emploie les
tuiles passées par le mandataire. Dans le navigateur, le contrôleur retire les tuiles du mandataire avant que la carte
soit faite et ne les rend que si le paquet ne se charge pas.

## Une couche de base propre à l'application

Une autre source est un `BaseLayer` ; créez-en un alias :

```yaml
services:
    Phil\MapBundle\Application\Contract\BaseLayer: '@App\Maps\SatelliteTiles'
```
