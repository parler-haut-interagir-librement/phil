<!-- phil-dev source-sha256=c9fe495da8b2ec6aef346404468ad4a4fb740789eef8da3716867603f50322f5 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste chaque analyseur lexical sur des attentes exactes (chaque jeton, écrit `{type:text}`) pour
chaque langage et chaque branche de sa lecture, chaque mot de chaque liste fournie lu comme le dit sa liste, le
curseur et la sortie, le HTML et le colorateur, et les lignes : leurs balises span, numéros, plages et annotations dans
chaque sorte de commentaire, les plages de lignes et leurs bornes. Un test de robustesse donne à chaque langage des
sources qui n'ont pas été écrites pour lui, vérifie qu'aucun octet n'est perdu et qu'aucun jeton n'est vide, ni
qu'aucune ligne ne se perd quand le bloc s'écrit ligne par ligne, et lit des sources hostiles (dix mille guillemets,
crochets ou marques ouverts) en moins d'une seconde chacune.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/highlight/tests
```

La bibliothèque est tenue par Infection à un score de mutation couverte de 100 % ; le test de robustesse en est
exclu.

## Fuzzing

Deux cibles de fuzzing lisent des entrées que personne n'a écrites, à partir des graines de
`tests/Fuzz/corpus/<target>` : `line-ranges`, dont les plages se relisent à l'identique ou sont refusées par
`InvalidLineRanges`, et `line-annotations`, les lignes d'un bloc en cinq langages avec leurs annotations lues, une
`hl-line` par ligne et, sans annotations, le code rendu octet pour octet. Depuis la racine du monodépôt :

```sh
composer fuzz -- line-ranges line-annotations
```

## Bancs d'essai

`HighlighterBench` colore des sources de 1, 10 et 100 Ko en PHP, JavaScript, HTML, CSS, YAML, Twig et SQL, ainsi que
des sources faites de chaînes et de commentaires laissés ouverts ; il construit aussi le colorateur par défaut. Le
temps croît avec la longueur de la source. Depuis la racine du monodépôt :

```sh
composer bench -- packages/highlight/tests/Benchmark
```

En mode *worker*, `tests/Worker/worker.php` est une page que FrankenPHP sert depuis un seul processus d'une requête à
l'autre, en colorant 10 Ko de PHP et 10 Ko de JavaScript ; les services qu'il construit avant sa boucle servent toutes
les requêtes, comme dans le *worker* d'une application :

```sh
composer bench:worker phil/highlight
```

## Tester du code qui colore

La sortie est déterministe. Comparez-la exactement, ou comparez des jetons :

```php
self::assertSame('<span class="hl-keyword">echo</span> <span class="hl-number">1</span>', Highlighter::default()->toHtml('echo 1', 'php'));
```

Le test d'une page qui affiche du code n'a pas à dépendre des couleurs : vérifiez que le texte du code s'y trouve,
échappé, et que le bloc porte sa classe `language-…`.
