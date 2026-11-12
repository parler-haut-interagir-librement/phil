<!-- phil-dev source-sha256=881afaf34e733e0a11834f64777038a5b093fc46b837d08bc648a68e344bd959 -->
# Exceptions

Chaque exception implémente `Phil\TranslationMemory\Exception\TranslationMemoryException`, et chacune étend
`InvalidArgumentException`.

| Exception | Lancée quand |
|---|---|
| `InvalidLanguage` | Une étiquette de langue n'est pas du BCP 47 tel que la bibliothèque le lit ; `XliffMerge::merge()` reçoit une langue que le fichier n'a pas ; `Variants` reçoit une langue primaire seule |
| `InvalidSegment` | Une clé de segment est vide, dépasse 255 octets ou n'est pas en UTF-8 ; un texte n'est pas en UTF-8 ; l'état et la cible ne s'accordent pas ; on demande une révision ou une confirmation à un segment qui ne peut pas l'accepter ; une empreinte relue n'en est pas une ; une unité de mémoire a une source ou une cible sans mots |
| `InvalidTerm` | Un terme, une traduction, une traduction refusée ou une forme du glossaire n'a pas de mots, ou n'est pas en UTF-8 |
| `InvalidXliff` | Un fichier XLIFF n'est pas du XML bien formé, déclare un type de document, n'est ni du XLIFF 1.2 ni du 2.x, n'a pas de `<file>` en 1.2, ou nomme une langue qui n'est pas une étiquette |
| `InvalidTmx` | Un fichier TMX n'est pas du XML bien formé avec une racine `<tmx>`, déclare un type de document, n'a pas son `<header>` ou son `<body>`, nomme une langue qui n'est pas une étiquette, ou contient plus de 100 000 unités |
| `InvalidTbx` | Un fichier TBX n'est pas du XML bien formé avec une racine `<tbx>` ou `<martif>`, déclare un type de document, nomme une langue qui n'est pas une étiquette, ou contient plus de 100 000 entrées |
| `InvalidConfiguration` | `TranslationMemory` est construit avec un score minimum qui n'est pas supérieur à 0 et au plus 1, ou une limite ou une présélection inférieure à 1 ; un indicateur n'en est pas un, ou un segment en a plus de 32 ; `AdvanceWidths` ou `MaxSize` reçoit un réglage hors limites |

Les messages nomment ce qui ne va pas :

```text
"fr_CA" is not a language tag such as "en", "fr-CA" or "zh-Hant-TW".
Segment "b1/text" is stale: only a target written for its source can be reviewed.
The XLIFF file cannot be read: a document type declaration is not allowed.
The target language is "fr", but "de" was given.
```
