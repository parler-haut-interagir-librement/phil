<!-- phil-dev source-sha256=34edbbc0940646531454e8b5df7f470e9d7b1ddc56b0b07e199a930061fec3f7 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, convertit chaque construction vers le MathML attendu (macros, couleurs, liens, numéros,
filets et chimie dans `ExtensionsTest`). Elle vérifie chaque refus avec sa position, ainsi que les limites de longueur,
de profondeur, de cellules et d'expansion. Dans le monodépôt, Infection maintient le paquet à 100 % de mutants
couverts.

## Fuzzing

`tests/Fuzz/latex-mathml.php` donne au convertisseur des entrées que personne n'a écrites, à partir des germes de
`tests/Fuzz/corpus/latex-mathml`, dont des macros, des liens et de la chimie : quoi qu'il lise, il retourne du MathML ou lance `InvalidLatex`, rien d'autre.

```sh
composer fuzz -- latex-mathml --budget=60     # from the monorepo
```
