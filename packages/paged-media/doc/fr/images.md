<!-- phil-dev source-sha256=81be73b6b846a39d844eeb20955250a8cb7663cbe657d87f0b0ea7e42bfc2a1b -->
# Images

```php
$logo = Image::fromFile('/path/to/logo.png');
$pdf->image($logo, 54, 720, 120, 40);
```

`Image::fromFile()` et `Image::fromBytes()` lisent les fichiers JPEG et PNG.

| Fichier | Façon dont il est écrit |
|---|---|
| JPEG, gris, RGB ou CMYK | Tel quel, décodé par le lecteur ; un JPEG CMYK d'Adobe avec ses valeurs inversées |
| PNG sans transparence — gris, RGB ou indexé, de 1 à 16 bits | Ses lignes compressées telles quelles, avec le prédicteur que le lecteur défait |
| PNG avec canal alpha — gris ou RGB, 8 ou 16 bits | Décodé, puis écrit comme une image couleur et un masque alpha |

Un PNG entrelacé est refusé : l'enregistrer sans entrelacement permet de le placer. La transparence d'un PNG indexé
(`tRNS`) est ignorée, tout comme les profils de couleur et le gamma.

Une image est placée à la taille donnée, quels que soient ses pixels : un logo de 600 pixels de large placé sur
150 points de large s'imprime à 288 pixels par pouce.
