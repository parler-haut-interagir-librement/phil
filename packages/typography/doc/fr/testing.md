<!-- phil-dev source-sha256=e7cc13726bb629e226415a3d47c82bb94d4c4bebc8a77583c84c4c93a1f68920 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste chaque correcteur sur ses propres cas et à travers les parties d'une suite, le typographe
sur des fragments HTML, des documents et du texte brut, le césureur sur de petits motifs écrits à la main et sur ceux
qui sont fournis, chaque langue fournie lue une fois, et des séquences d'émojis dans chaque préréglage. Chaque langue
dont les motifs sont écrits pour cette bibliothèque a une liste d'au moins quarante mots courants qui doivent se
couper exactement là où ses règles le permettent, dans ses longueurs minimales. Un test vérifie que les fichiers sous
`data/hyphenation/` sont ceux que `bin/generate-patterns.php` écrit à partir des règles.

À partir de la racine du monodépôt :

```sh
vendor/bin/phpunit packages/typography/tests
```

Infection maintient la bibliothèque à un score de mutation couverte de 100 %.

## Bancs d'essai

`TypographerBench` corrige des fragments HTML de 1, 10 et 100 Ko en anglais et en français, avec césure, et des textes
faits de guillemets, de points et de tirets laissés ouverts ; il construit aussi le typographe français. Le temps
croît avec la longueur du fragment. À partir de la racine du monodépôt :

```sh
composer bench -- packages/typography/tests/Benchmark
```

En mode *worker*, `tests/Worker/worker.php` est une page que FrankenPHP sert à partir d'un seul processus d'une requête
à l'autre, en corrigeant 10 Ko de HTML français et 10 Ko de HTML anglais. Les services qu'il construit avant sa boucle
servent chaque requête, comme dans le *worker* d'une application. Il a besoin de l'extension intl dans l'image
FrankenPHP :

```sh
composer bench:worker phil/typography
```

## Tester du code qui corrige du texte

La sortie est déterministe : le même typographe donne la même chaîne. Comparez-la exactement, en écrivant les
caractères invisibles par leurs échappements, pour que le test dise ce qu'il attend :

```php
self::assertSame("<p>«&nbsp;Oui&nbsp;»\u{202F}!</p>", Typographer::for('fr_FR', false)->fix('<p>"Oui" !</p>'));
```

Le sérialiseur HTML5 écrit l'espace insécable `&nbsp;` et tout autre caractère tel quel : l'espace fine insécable
(U+202F) et le trait d'union conditionnel (U+00AD) apparaissent bruts. Construisez le typographe sans césure dans les
tests qui ne la vérifient pas, pour qu'un changement de motifs ne change pas des attentes sans rapport.
