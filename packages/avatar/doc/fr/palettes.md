<!-- phil-dev source-sha256=0d745c7276e3d6692a71f1da04d4c1c0917a10b3fb1ba86d9bb27acf5105ec71 -->
# Palettes et contraste

Une palette contient les fonds sur lesquels un avatar est dessiné, choisis par le germe, et deux encres : une claire et
une foncée, blanche et `#111111` par défaut. Les initiales, et les carrés du style des pixels, prennent l'encre du plus
fort contraste avec leur fond.

## La règle

Chaque fond d'une palette atteint un contraste d'au moins 4,5:1 avec l'une de ses encres : le niveau WCAG 2.2 AA du
texte normal, plus strict que le 3:1 du grand texte que demanderaient les initiales à la plupart des tailles. `Palette`
refuse un fond qui ne l'atteint avec aucune des deux encres, de sorte que les initiales sont toujours lisibles, quel que
soit le germe.

Le contraste se calcule comme le définit WCAG : la luminance relative de chaque couleur sRGB, puis
`(L1 + 0.05) / (L2 + 0.05)`, la plus claire sur la plus foncée. `Color::contrastWith()` le donne.

## Les palettes fournies

| Nom | Fonds |
|---|---|
| `default` | Douze : huit couleurs profondes sous des initiales blanches, quatre claires sous des initiales foncées. |
| `pastel` | Dix couleurs claires, sous des initiales foncées. |
| `vivid` | Dix couleurs saturées. |
| `earth` | Dix bruns, verts et sables. |
| `grayscale` | Huit gris. |

`Palette::named($name)` en donne une ; `Palette::names()` les énumère.

## Une palette de l'application

```php
$palette = Palette::fromHex(['#0b3d91', '#e0e7ff', '#7c2d12'], lightInk: '#ffffff', darkInk: '#0f172a');

$avatars->svg($seed, new AvatarOptions(name: $name, palette: $palette));
```

Les couleurs s'écrivent `#rrggbb` ou `#rgb`. Une palette contient de 1 à 64 fonds. Le style des formes dessine ses
formes dans les autres fonds de la palette, ou dans son encre quand la palette n'a qu'un fond.
