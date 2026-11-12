<!-- phil-dev source-sha256=58164f2a6f652fb79254f0b1f130dcf6a6a26a0d9fa3f8db5f44118509f4a6a5 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Locales` | Les locales vérifiées et jointes par `_`, une valeur pour une locale, sa langue ou un repli, les textes par locale et leurs bornes |
| `SiteIdentity`, `Field`, `DocumentType` | Les valeurs épurées, les champs omis, les valeurs refusées, les champs qui manquent à chaque document |
| `LegalDocumentVersion` | Les bornes, les dates de chaque état, la publication et le retrait, les versions refusées |
| `DocumentHistory` | La version en vigueur et la prochaine, les égalités de date, les versions retirées et les brouillons, les historiques refusés |
| `RetentionDuration`, `DurationWords`, `ConsentCategory` | Les durées en toutes lettres en anglais et en français, les durées, conservations et catégories refusées |
| `Placeholders`, `PlainTextBody` | Les valeurs échappées et insérées dans le texte seulement, jamais dans une balise, un commentaire ou un script, les noms trouvés, les valeurs d'une version, les paragraphes et les sauts de ligne |
| `LegalNotices` | Le rendu dans une locale, le repli, l'historique d'un autre document, les versions publiées dans un intervalle, la vérification |

```sh
vendor/bin/phpunit packages/legal-notices/tests
```

## Fuzzing

Deux cibles sont déclarées dans `phil-dev.php`, avec leurs germes sous `tests/Fuzz/corpus/<target>` :

| Cible | Ce qu'elle vérifie |
|---|---|
| `placeholders` | Le rendu de texte brut et le remplissage des paramètres retournent une chaîne pour toute entrée, et une valeur est toujours échappée |
| `durations` | Une durée est lue en toutes lettres ou refusée avec `InvalidLegalNotice`, jamais autre chose |

```sh
composer fuzz -- placeholders durations
```
