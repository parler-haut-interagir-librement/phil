<!-- phil-dev source-sha256=f4dabf752127909ce67aff3026b5c40d1c3029b81aef5e6280a2fac7d17d06b2 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Numbering` | Chaque style, les limites des chiffres romains et des tours de symboles, les refus |
| `NoteOptions`, `NoteLabels` | Les plages des options, la recherche par locale et par langue, les deux langues |
| `NoteResolver` | L'ordre de lecture, les blocs imbriqués et les éléments de liste, les blocs littéraux, chaque redémarrage, chaque problème, les identifiants faits de chiffres |
| `Html\NotesRenderer` | Le HTML exact de chaque emplacement, les liens de retour, les sections, l'assainissement, les liens dans des liens, les espaces, les préfixes, l'échappement |
| `Text\NotesPlainText` | Les renvois marqués, les notes listées, les blocs littéraux, les conversions de l'appelant |
| `Stylesheet` | Les règles de GCPM, la colonne de marge, la bascule, l'animation soumise à `prefers-reduced-motion` |

Infection impose à la bibliothèque 100 % de mutants couverts tués.

```sh
vendor/bin/phpunit packages/footnotes-sidenotes/tests
```

## Bancs d'essai

`NoteResolverBench` mesure la résolution, le rendu en fin de document, le rendu en pied de page et le texte brut de
livres de 100, 1 000 et 5 000 notes. À la racine du monodépôt :

```sh
composer bench -- packages/footnotes-sidenotes/tests/Benchmark
```

## Fuzzing

La cible `footnotes-sidenotes` décode, résout et produit le rendu de toute entrée dans chaque emplacement, à partir des
germes de `tests/Fuzz/corpus`.

```sh
composer fuzz -- footnotes-sidenotes
```
