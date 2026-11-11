<!-- phil-dev source-sha256=2e94ed840275a6c456eb8688afd9a9d36d298dd8844ae01bfdf2f0ffb14b00f9 -->
# Maquettes

Une maquette est la photo d'un produit vierge sur laquelle des visuels sont placés, comme un client voit le produit sur
un site.

## Photos et zones

Une `FacePhoto` est la photo d'une face du produit dans une variante — le devant du t-shirt en noir — avec le rectangle
que couvre chaque zone d'impression sur elle, en pixels :

```php
use Phil\PrintCompositor\FacePhoto;
use Phil\PrintCompositor\PixelBox;
use Phil\PrintCompositor\Raster;

$photo = new FacePhoto('front', 'black', Raster::fromFile('/path/to/front-black.jpg'), [
    'front' => new PixelBox(312, 260, 400, 500),
]);
```

Chaque variante a sa photo : une zone peut donc se trouver à d'autres pixels sur une autre photo. Une zone qui dépasse
sa photo est refusée. Une `PixelBox` aux proportions de la taille réelle de la zone garde le visuel à ses proportions
d'impression.

## Placements

Un `Placement` met un visuel dans une zone de la photo ; le visuel est contenu dans le rectangle de la zone, ses
proportions gardées, centré horizontalement et en haut par défaut, comme on aligne une impression sur un vêtement. Le
fichier d'impression couleur de la zone est le visuel habituel : il a les proportions de la zone et remplit exactement
le rectangle.

Une `Mockup` est la photo, ses placements et l'intensité de l'ombrage, de 0 à 1, 0,8 par défaut. Un placement dans une
zone que la photo ne place pas est refusé.

## Ombrage

`MockupCompositor::compose()` dessine chaque visuel sur une copie de la photo. Chaque pixel du visuel est multiplié par
une nuance lue dans la photo sous lui :

```
shade = 1 + strength × (luminance of the photo pixel ÷ mean luminance of the zone − 1)
```

plafonnée à `MockupCompositor::MAX_SHADE`, 2, et chaque canal à 255, puis fondue avec la photo selon l'opacité du
visuel. La luminance pondère le rouge, le vert et le bleu comme le fait l'ITU-R BT.709.

Un pli plus foncé que le tissu qui l'entoure assombrit l'impression, un reflet l'éclaircit, une zone plane la laisse
telle que dessinée. La nuance est relative à la moyenne propre du tissu, contrairement à une fusion en produit : une
impression garde ses couleurs sur un vêtement noir comme sur un blanc, et seuls les plis transparaissent. Une zone d'un
noir pur laisse l'impression telle que dessinée. La transparence propre de la photo, pour un produit détouré de son
fond, est gardée.

Les pixels sont lus et écrits en PHP : une zone de 400 × 500 pixels est ombrée en une fraction de seconde. Les
maquettes de chaque variante d'un produit sont générées une seule fois, en même temps que le produit, et non à chaque
affichage d'une page.

## WebP

Le résultat est un `Raster` de la taille de la photo ; `webp()` l'encode pour un site, à la qualité 82 par défaut :

```php
$mockup = new MockupCompositor()->compose(new Mockup($photo, [new Placement('front', $files->raster())]));
file_put_contents('front-black.webp', $mockup->webp());
```
