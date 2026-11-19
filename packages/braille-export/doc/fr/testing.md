<!-- phil-dev source-sha256=1df1e33554d72f2b9efd2f35fdff6f8546fb1690589bcbf5500b080867ce0466 -->
# Tests

| Zone | Ce qui est couvert |
|---|---|
| `UncontractedTranslator` | Chaque lettre, lettre accentuée, majuscule, mot en majuscules, nombre, signe dans un nombre et après un nombre, et signe de ponctuation de chaque code ; lettres ramenées à leur base ; caractères omis ou refusés en mode strict ; étiquettes de langue lues ; contractions et langues refusées |
| `ChainTranslator` | Le premier traducteur qui prend en charge la langue, le repli, aucun |
| `BrailleExporter` | Le retrait de chaque bloc, les sauts de ligne, les espaces blancs réduits, les mots coupés avec et sans leur propre trait d'union et jamais après une cellule d'annonce, les titres de chaque niveau gardés avec leur ligne suivante, les listes imbriquées et ordonnées, les tableaux linéarisés en anglais et en français, les numéros de page et leur place, les pages imprimées et leurs lignes, les sauts de page, les sections, les volumes, la limite de pages, un lot par document, la réponse d'un traducteur vérifiée |
| Écrivains | Chacune des 64 cellules en BRF, les cellules à huit points refusées, le PEF et ses métadonnées vérifiés contre XML, son identifiant dérivé du contenu, l'aperçu Unicode |
| `UjoReader` | Chaque bloc UJO lu, données manquantes ou erronées lues comme des valeurs par défaut, un autre texte brut |
| `PageFormat`, les blocs, `Braille` | Leurs bornes |

Les tests unitaires, sous `tests/Unit`, n'ont besoin d'aucun programme, d'aucun noyau et d'aucun réseau :

```sh
vendor/bin/phpunit packages/braille-export/tests/Unit
```

Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## Fuzzing

La mise en page est fuzzée depuis la racine du monodépôt avec n'importe quel texte, en français et en anglais, mis en
page sous la forme de chaque sorte de bloc sur une page de 12 cellules sur 4 lignes ; une rangée qui n'est pas du
braille, une rangée trop large, une page trop longue ou un fichier BRF impossible à écrire arrête l'exécution :

```sh
composer fuzz -- layout
```

## Banc d'essai

`tests/Benchmark/ExportBench.php` met en page un chapitre de 200 paragraphes français, environ 90 pages braille, et
l'écrit en BRF et en PEF :

```sh
composer bench -- --filter=ExportBench
```
