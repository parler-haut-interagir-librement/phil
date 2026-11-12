<!-- phil-dev source-sha256=9d5e11457343aa0ebd170252990e7ff380aab4945a3afcde432ff9b22b58e4d2 -->
# Exceptions

Les exceptions du bundle implémentent `Phil\TranslationBundle\Domain\Exception\TranslationBundleException`.

| Exception | Étend | Lancée quand |
|---|---|---|
| `Domain\Exception\InvalidRecord` | `InvalidArgumentException` | Une référence de document ne fait pas de 1 à 255 octets ; un terme dépasse 255 octets ; une ligne reçoit un segment d'une autre clé ou un autre terme ; une personne est désignée par rien ou par plus de 255 octets ; des indicateurs dépassent 1024 octets ; un commentaire est vide, dépasse 4000 caractères ou n'est pas en UTF-8 |
| `Application\Exception\NotFound` | `RuntimeException` | Un segment que le document n'a pas ; un document non préparé dans la langue, exporté, importé ou traduit automatiquement ; un terme que le glossaire n'a pas ; un commentaire que le document n'a pas |
| `Application\Exception\MachineTranslationFailed` | `RuntimeException` | La traduction automatique alors qu'elle est désactivée ; un traducteur qui lance une exception ou qui ne répond pas une cible par source |

`Translations` laisse aussi passer l'`InvalidSegment` de la bibliothèque (un changement qu'un segment ne peut pas
accepter), `InvalidXliff` et `InvalidLanguage` (un fichier illisible, ou d'autres langues), et `InvalidConfiguration`
(un contrôle qui n'est pas le nom d'un indicateur) ; `Glossaries` laisse passer `InvalidTbx`. Les messages nomment ce
qui ne va pas :

```text
Document "article:42" has no segment "b9/text" in "fr".
Document "article:42" has no segments in "fr": prepare it first.
The glossary from "en" to "fr" has no term "dragon".
```

L'écran les transforme en réponses HTTP, comme l'énumère [l'écran de traduction](screen.md).
