<!-- phil-dev source-sha256=b73868dcf9176ed5380b057f4eda69ac6768a5ee8b6613597b64f3e6a1785626 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` dessine de petites images pixel par pixel et compare chaque pixel des résultats : la mise à
l'échelle et l'alignement des images dans leurs boîtes, l'ordre des calques et des éléments, la fusion des pixels
translucides, un fichier par encre, l'ombrage exact d'une maquette sur des colonnes grises à plusieurs intensités, ses
plafonds, une zone noire et une photo transparente. Les textes sont dessinés dans une police TrueType que les tests
écrivent eux-mêmes, dont les glyphes sont des rectangles de tailles connues, avec des approches et des jambages
descendants : la taille que prend un texte, ses lignes, son encre et son alignement sont vérifiés au pixel près à
72 ppp, où un point vaut un pixel. Ses formes en collection et en WOFF vérifient les polices refusées.

À partir de la racine du monodépôt :

```sh
vendor/bin/phpunit packages/print-compositor/tests
```

Infection tient la bibliothèque à un score de 100 % des mutants couverts.

## Bancs d'essai

`PrintCompositorBench` compose une zone de 100 × 100 mm, une image sous un texte ajusté, à 72, 150 et 300 ppp, et
ajuste le texte seul aux mêmes résolutions. À partir de la racine du monodépôt :

```sh
composer bench -- packages/print-compositor/tests/Benchmark
```

En mode *worker*, `tests/Worker/worker.php` est une page que FrankenPHP sert depuis un seul processus d'une requête à
l'autre, en composant la même zone à 150 ppp dans un fichier PNG ; le compositeur et la police qu'il construit avant sa
boucle servent chaque requête. Il a besoin de l'extension GD, avec FreeType, dans l'image FrankenPHP :

```sh
composer bench:worker phil/print-compositor
```

## Tester du code qui compose

Composez de petites tâches à basse résolution et examinez des pixels plutôt que des octets encodés, qui changent selon
les versions de GD et de libpng :

```php
use Phil\PrintCompositor\Box;
use Phil\PrintCompositor\Colour;
use Phil\PrintCompositor\ImageItem;
use Phil\PrintCompositor\Layer;
use Phil\PrintCompositor\PrintCompositor;
use Phil\PrintCompositor\PrintJob;
use Phil\PrintCompositor\Raster;
use Phil\PrintCompositor\Zone;

$red = Raster::blank(1, 1, Colour::fromHex('#ff0000'));
$job = new PrintJob(new Zone('front', 25.4, 25.4), [new Layer('artwork', [new ImageItem($red, new Box(0, 0, 25.4, 25.4))])], 10);
$file = new PrintCompositor()->compose($job)->raster();

self::assertSame(10, $file->width);
self::assertEquals(Colour::fromHex('#ff0000'), $file->pixel(5, 5));
```

Pour tester le refus du texte d'un client, ajustez un `TextItem` avec `TextFitter` et attendez `TextDoesNotFit` ou
`MissingGlyphs` : aucun fichier n'est dessiné.
