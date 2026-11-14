<!-- phil-dev source-sha256=9956dd5eb3fd6224842eeb1db12292a2ea4ea8f809500661118a20d6d3bfd06a -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, vérifie la lecture des configurations et chaque refus, les graduations rondes de
l'échelle, la légende, l'éclaircissement des libellés, les arcs des pointes, et des images entières par rapport à des
fichiers de référence dans `tests/Unit/Fixture`, examinés à leur écriture. Dans le monodépôt, Infection maintient le
paquet à 100 % de mutants couverts.

Les vérifications de `ResvgRasterizer` — limites, polices, nettoyage, ordre de la mesure et du dessin — s'exécutent
partout, avec un moteur qui ne dessine rien. Les tests du moteur lui-même portent `#[RequiresPhpExtension('resvg')]` et
s'exécutent là où l'extension est chargée : la taille du PNG d'un graphique à chaque échelle, les refus du moteur, et
les fichiers qu'il ne lit jamais, un chemin absolu, une sortie de son répertoire et une adresse distante, chacun dessiné
comme rien à côté de la même image sans confinement. La police de test, `tests/Unit/Fixture/fonts/test.ttf`, a été
faite pour les tests.

## Tester du code qui dessine des graphiques

L'image est déterministe : comparez-la avec un fichier de référence, ou cherchez le `<title>` d'une marque. Le code qui dessine des images PNG prend un
`Contract\Rasterizer` : donnez-lui, dans ses tests, un rasterizer qui renvoie des octets connus et garde le SVG qu'on lui a demandé.
