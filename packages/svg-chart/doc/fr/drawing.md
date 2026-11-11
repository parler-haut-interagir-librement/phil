<!-- phil-dev source-sha256=2228c7a041bf5882e289cbc8c56c41f1db4bf6ef9b262c2875747d799c6237da -->
# Dessin

`SvgChartWriter` dessine un `Chart` dans une image SVG autonome.

```php
new SvgChartWriter(int $width = 640, int $height = 400, array $palette = SvgChartWriter::PALETTE, string $font = 'system-ui, sans-serif')->write(Chart $chart): string
```

| Option | Plage |
|---|---|
| `$width`, `$height` | 100 à 4 000 pixels, le `viewBox` de l'image |
| `$palette` | une ou plusieurs couleurs `#rrggbb`, prises tour à tour par les séries ou les pointes |
| `$font` | des familles de polices, en lettres, chiffres, espaces, virgules et tirets |

## Ce qui est dessiné

- **Barres** : un groupe par libellé, une barre par série, depuis la ligne du zéro ; l'axe contient toujours zéro.
- **Lignes** : un tracé par série passant par le centre de chaque libellé, interrompu là où une valeur est `null`, un
  point par valeur.
- **Graphiques circulaires et en anneau** : la première série, une pointe par libellé dans le sens horaire depuis le
  haut ; une valeur négative ou manquante compte pour rien, et un graphique dont les valeurs totalisent zéro est un
  anneau vide.

L'axe des valeurs a environ cinq graduations rondes — 1, 2 ou 5 fois une puissance de dix —, et les libellés trop
larges pour leur bande sont affichés un sur deux, ou moins. La légende énumère les séries d'un graphique à axes quand
il y en a plusieurs ou une seule nommée, et les libellés d'un graphique circulaire ; ses entrées passent à la ligne en
rangées. Les largeurs de texte sont estimées, à 0,6 em par caractère.

La palette par défaut est l'ensemble Okabe-Ito, dont les couleurs se distinguent avec toutes les visions des couleurs
courantes.

## Accessibilité

L'image porte `role="img"`, le titre du graphique comme `<title>` (ou sa sorte, « Bar chart »), et une `<desc>` qui
lit chaque valeur : « Bar chart. Sales: Jan 12, Feb 19. » Chaque barre, point et pointe a un `<title>` avec sa série,
son libellé et sa valeur — et sa part pour une pointe —, que les navigateurs affichent au survol.

## Sécurité

Chaque texte est échappé pour le XML, les couleurs et la police sont vérifiées, et rien d'autre que des formes et du
texte SVG n'est écrit : l'image peut s'écrire sans risque directement dans une page.
