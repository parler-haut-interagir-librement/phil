<!-- phil-dev source-sha256=de4ebf0d76025bd8d637cc57e4dc59a5b766a40deb0a6f7142e02879e5fbec92 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste l'analyseur lexical sur chaque genre de jeton, chaque échappement, chaque chaîne et URL
invalide, et sur les lignes qu'il compte. Elle teste l'analyseur sur les règles, les déclarations, `!important`, les
erreurs d'analyse et sa borne d'imbrication. Elle teste le sérialiseur sur les échappements, sur les commentaires entre
jetons — chaque paire du tableau de la section 9 — et sur la relecture de ce qu'il a écrit. Elle teste chaque forme de
sélecteur, permise ou refusée. Elle teste enfin l'assainisseur sur chaque règle @, chaque raison de retrait, les URL, la
portée, les limites, et du CSS qui tente de fermer son élément `<style>`. `NestingTest` et `ImportsAndImageSetsTest`
contiennent les cas de sécurité des règles imbriquées — `&` qui sortirait de la portée, imbrication au-delà des niveaux
de la politique, imbrication au-delà de la limite de profondeur —, d'`@import`, et des URL d'`image-set()` à côté de
`type()`.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/css-sanitizer/tests
```

La bibliothèque est tenue à un score de mutation couvert de 100 % par Infection.

## Fuzzing

Les cibles de fuzzing `css-sanitizer-sheet` et `css-sanitizer-declarations` donnent à l'assainisseur des entrées que
personne n'a écrites, à partir des germes de `tests/Fuzz/corpus/`. Quoi qu'il lise, il renvoie ce qu'il a gardé et ne
lève jamais d'exception. La cible `css-sanitizer-nesting` fait de même sous une politique qui garde les règles
imbriquées, `@import` et `image-set()`, et vérifie que la feuille assainie, assainie de nouveau, revient entière et
inchangée. Depuis la racine du monodépôt :

```sh
composer fuzz -- css-sanitizer-sheet css-sanitizer-declarations css-sanitizer-nesting
```

## Bancs d'essai

`CssSanitizerBench` assainit des feuilles de style de 1, 10 et 100 Ko, des feuilles de propriétés refusées, et des
feuilles faites de commentaires, de chaînes et de blocs laissés ouverts. Le temps croît avec la longueur de la feuille.
Depuis la racine du monodépôt :

```sh
composer bench -- packages/css-sanitizer/tests/Benchmark
```

## Tester du code qui assainit du CSS

La sortie est déterministe. Comparez le CSS exactement, et les retraits par raison et par sujet :

```php
$sanitized = new CssSanitizer()->sanitize('a { color: red; position: fixed }');

self::assertSame("a {\n  color: red;\n}\n", $sanitized->css);
self::assertSame(
    [[RemovalReason::Property, 'position']],
    array_map(static fn (Removal $removal): array => [$removal->reason, $removal->subject], $sanitized->removals),
);
```

Pour tester un réécrivain, donnez-le à une politique et assainissez une déclaration qui contient une URL. Pour tester un
formulaire, utilisez le `ConstraintValidatorTestCase` de Symfony avec `SafeCssValidator`, comme le fait le test propre
à la bibliothèque.
