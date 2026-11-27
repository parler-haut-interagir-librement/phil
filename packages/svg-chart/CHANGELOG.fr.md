<!-- phil-dev source-sha256=b06b7e5a43c9192ef782899bf0755bc532f6d72ad95242e784396be69bbbd274 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/svg-chart` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/), et les versions suivent le
[versionnage sémantique](https://semver.org/).

## 0.1.1-alpha - 2026-11-15

### Ajouté

- `Contract\Rasterizer`, le port des images PNG, et `Raster\ResvgRasterizer`, qui dessine du SVG en PNG par l'extension
  PHP facultative `resvg`, à une échelle de 1 à 4 : chaque image assainie par phil/svg-sanitizer sans les images
  matricielles des adresses `data:`, les polices données lues par phil/fonts et aucune police du système, les images
  qu'un SVG nomme confinées à un dossier vide, les limites d'octets et de pixels vérifiées avant que rien soit dessiné ;
  `isAvailable()`.
- `RasterizerUnavailable`, `InvalidRasterizer` et `RasterizationFailed`.
- `ext-resvg` suggérée ; les tests du moteur de rendu s'exécutent là où elle est chargée.
- La documentation des images PNG, dans `doc/png.md`.

### Modifié

- phil/svg-sanitizer et phil/fonts sont exigés.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Chart`, `ChartType` et `Dataset`, et `Chart::fromConfig()`, qui lit le type, les libellés, les séries, les couleurs
  et le titre d'une configuration Chart.js, ignore les autres options, et refuse plus de 500 libellés, 24 séries ou des
  textes de 200 caractères, avec le chemin de la valeur dans le message ; une couleur autre que `#rrggbb` est refusée
  quelle que soit la façon dont le graphique est construit.
- `SvgChartWriter` : des barres groupées à partir de la ligne zéro, des lignes coupées aux valeurs manquantes, des
  secteurs et des anneaux tirés de la première série, des graduations rondes, des libellés éclaircis, une légende en
  rangées, la palette Okabe-Ito ou une palette donnée, une police et une taille.
- Un `<title>` sur chaque marque, une `<desc>` de chaque valeur et `role="img"` ; chaque texte échappé.
- `InvalidChart`, sous l'interface `SvgChartException`.
- Des tests de chaque refus et des images de référence de chaque sorte de graphique.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README ; `CONTRIBUTING.md`, `SECURITY.md` et
  `CODE_OF_CONDUCT.md`.
