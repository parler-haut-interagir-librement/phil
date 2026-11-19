<!-- phil-dev source-sha256=f542247079525f3a9482a35f25d6fcebee8bc0d542f40282523f49bf98ef3a2f -->
# Exceptions

Chaque exception du paquet implémente `Exception\BrailleExportException` ; celles d'une traduction qui n'a pu se faire
implémentent `Exception\TranslationFailed`, qu'implémentent aussi les traducteurs des autres paquets.

| Exception | Quand |
|---|---|
| `UnsupportedTranslation` | Aucun traducteur ne prend en charge la langue avec la contraction demandée. |
| `UntranslatableCharacter` | Le `UncontractedTranslator` strict a rencontré un caractère sans braille ; `$character` et `$language` le nomment. Une `TranslationFailed`. |
| `InvalidBraille` | Un traducteur a rompu le port : pas un texte par texte envoyé, ou des caractères qui ne sont pas des cellules braille. |
| `DocumentTooLong` | Le document remplit plus de pages que `PageFormat::$maxPages`. |
| `UnrepresentableCell` | `BrfWriter` a rencontré une cellule avec le point 7 ou 8. |

`InvalidArgumentException` arrête une valeur construite hors de ses bornes : un `PageFormat`, un niveau de titre hors de
1 à 6, une liste ordonnée qui commence sous 0, des points qui ne sont pas de 1 à 8.

Les messages nomment l'échec, jamais le texte du document.
