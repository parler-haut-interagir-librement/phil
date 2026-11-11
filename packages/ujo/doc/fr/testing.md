<!-- phil-dev source-sha256=190fcfd3d6e720300380d580d1e565b23eba075aa4bd1353d978a2f19d55ea73 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` vérifie chaque type par des cas pilotés par les données : ses données minimales complétées par
les valeurs par défaut, ses données complètes gardées dans l'ordre, chaque valeur obligatoire, chaque borne, chaque
choix, chaque longueur et chaque forme, chaque forme ancienne lue, et le HTML et le texte exacts de chaque type et de
chaque réglage. L'assainisseur est testé sur les pièges qu'un fragment peut contenir : des scripts dans n'importe quelle
casse, des adresses encodées, des éléments mal imbriqués ou non fermés, des guillemets dans les attributs.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/ujo/tests
```

La bibliothèque est tenue à un score de mutation couverte de 100 % par Infection.

## Fuzzing

La cible de fuzzing `ujo` donne au décodeur et aux moteurs de rendu des entrées que personne n'a écrites, à partir des
germes de `tests/Fuzz/corpus/` : un document est soit décodé et rendu, soit refusé par une exception de la
bibliothèque. Depuis la racine du monodépôt :

```sh
composer fuzz -- ujo
```

## Bancs d'essai

`UjoBench` décode, vérifie et produit le rendu de documents de 10, 100 et 1 000 blocs de chaque type courant, et d'un
paragraphe de balises en ligne imbriquées sur 1 000 et 5 000 niveaux ; chaque étape croît avec le nombre de blocs,
jamais avec son carré. Un document rendu tel que le normaliseur l'a retourné n'est pas vérifié de nouveau. Depuis la
racine du monodépôt :

```sh
composer bench -- packages/ujo/tests/Benchmark
```

## Tester du code qui stocke des documents

Dans le test, normalisez ce que l'éditeur enverrait, et comparez les formes canoniques :

```php
$ujo = new Ujo();
$stored = $ujo->encode($ujo->decode($postedJson));

self::assertSame('<p class="ujo-paragraph">Hello <b>world</b></p>', $ujo->toHtml($stored));
```

Un document construit à la main n'a pas besoin d'identifiants venus d'un éditeur : n'importe quel identifiant de 1 à
64 lettres, chiffres, `_` ou `-`, unique dans le document, convient.
