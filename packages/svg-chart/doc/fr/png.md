<!-- phil-dev source-sha256=c88a4525ec9305abe95ece3daa8d3f3220998d040052b9591bd06aa5588cbd54 -->
# Images PNG

Certains lecteurs n'acceptent pas le SVG : les clients de courriel, l'image qu'une page donne à partager, un document
qui ne prend que des images matricielles. `ResvgRasterizer` dessine une image SVG en PNG par l'extension PHP `resvg`,
dans le processus, sans navigateur ni service.

```php
use Phil\SvgChart\Raster\ResvgRasterizer;

if (ResvgRasterizer::isAvailable()) {
    $rasterizer = new ResvgRasterizer(['/srv/app/fonts/Inter-Regular.ttf', '/srv/app/fonts/Inter-Bold.ttf']);
    $png = $rasterizer->png(new SvgChartWriter()->write($chart), 2);
}
```

```php
new ResvgRasterizer(array $fontFiles, int $maxBytes = 1_048_576, int $maxPixels = 16_777_216)->png(string $svg, int $scale = 1): string
```

| Argument | Plage |
|---|---|
| `$fontFiles` | un ou plusieurs fichiers TrueType ou OpenType, collections comprises ; WOFF et WOFF2 sont refusés |
| `$maxBytes` | le plus grand SVG dessiné, de 1 à 16 777 216 octets |
| `$maxPixels` | le plus grand PNG dessiné, sa largeur fois sa hauteur à l'échelle demandée, de 1 à 67 108 864 |
| `$scale` | de 1 à 4 : le PNG a la taille propre de l'image fois l'échelle, 2 pour les écrans à haute densité |

Le rasterizer lit ses polices une seule fois, à sa construction : gardez-en un par *worker*, comme service. Il ne retient
rien d'une requête et peut servir chaque requête d'un *worker* de longue durée.

## L'extension est facultative

Le paquet fonctionne sans l'extension : les graphiques sont des images SVG, et seul le PNG n'est pas offert.
`ResvgRasterizer::isAvailable()` dit si l'extension est chargée ; sans elle, le constructeur lance
`RasterizerUnavailable`, dont le message le dit. L'extension s'installe avec PIE, qui la compile depuis ses sources
Rust pour PHP 8.3 à 8.5 ; voyez [Installation](installation.md).

`Contract\Rasterizer` est le port : le code qui a besoin d'un PNG prend un `Rasterizer`, et une application qui dessine
les images PNG autrement donne le sien.

## Sécurité

Un SVG dessiné en PNG est analysé et dessiné dans le processus PHP : chaque image est donc traitée comme si n'importe qui
l'avait écrite.

- Elle est refusée en entier quand elle dépasse `$maxBytes`, avant d'être lue.
- Elle passe par phil/svg-sanitizer : les scripts, les gestionnaires d'événements, les objets étrangers et toute
  référence qui n'est pas un fragment de l'image elle-même sont retirés, tout comme les images matricielles des adresses
  `data:`, qui pourraient cacher une image aux dimensions démesurées.
- Le moteur ne lit aucune police du système, seulement les fichiers donnés : la famille de la première dessine chaque
  texte dont les propres familles n'en font pas partie, familles génériques comme `sans-serif` et `system-ui`
  comprises, de sorte que la même image dessine les mêmes pixels sur toutes les machines.
- Les images que nomme un SVG sont confinées à un répertoire vide du paquet : aucun fichier de la machine n'est lu,
  quel que soit le chemin que donne le SVG. Le moteur n'a aucun accès au réseau.
- La taille du PNG est mesurée avant qu'il soit dessiné, et refusée au-delà de `$maxPixels`.
- Le moteur refuse un document sans taille, de plus de 1 000 000 d'éléments, ou plus grand que ses propres limites,
  `resvg.max_input_size` et `resvg.max_render_pixels` dans `php.ini`.

Chaque refus est une `RasterizationFailed`, avec la raison dans son message : rien n'est jamais dessiné en partie. Une
police illisible ou d'un autre format, ou une limite hors plage, est une `InvalidRasterizer`, à la construction du
rasterizer.

Les fichiers de polices sont une entrée de confiance : le moteur les analyse. Ne tirez jamais leurs chemins d'une
requête.

## Ce qui est dessiné

Le texte est mis en forme par le moteur : les accents, le grec, le cyrillique, et les écritures dont les lettres se
lient ou se réordonnent, comme l'arabe ou le devanagari, se dessinent comme les dessine un navigateur, pourvu qu'une
police donnée contienne leurs glyphes. Un graphique de `SvgChartWriter` est dessiné à la taille de son `viewBox`, fond
blanc compris. Le `<title>` et le `<desc>` du SVG ne survivent pas dans un PNG : donnez à l'image un texte `alt` là où
elle est affichée, et les valeurs en texte à côté.
