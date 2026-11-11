<!-- phil-dev source-sha256=a08962e54b084d1cd00817aec9042ddea062794082637ab6d3519c77a93ef94a -->
# Points d'extension

La bibliothèque est du PHP simple : elle ne déclare aucun service, et ses classes sont finales. Ce qu'une application
choisit se trouve dans les données qu'elle transmet.

| Point | Comment |
|---|---|
| Les thèmes et les catégories | La liste de base de `BaseCatalogue::load()`, complétée, modifiée ou remplacée par `CatalogueLoader::apply()` ou `applyFile()` avec les définitions de l'application |
| Les langues des libellés | Toute locale dans les libellés d'une définition ; les replis donnés à `label()` et à `description()` |
| L'endroit où les avertissements sont conservés | `ContentWarnings::toArray()` donne un tableau pour une seule colonne JSON, `fromArray()` le relit |
| L'endroit où les préférences sont conservées | `WarningPreferences::toArray()` et `fromArray()`, dans les réglages de membre ou la session de l'application |
| L'affichage d'un contenu visé pour un lecteur qui n'a rien choisi | `WarningPreferences::defaults($display)` |
| La lecture des thèmes depuis un autre outil | `Catalogue::themes()`, `categories()` et `themesIn()`, `WarningPreferences::themesWith()`, et `Display` — l'API publique où un outil de sécurité lit les lignes (`Hide`) et les voiles (`Fold`) |
| L'interception des erreurs de la bibliothèque | Chaque exception implémente `Phil\ContentWarnings\Exception\ContentWarningsException` |

Un avertissement ou une préférence stockés désignent un thème par sa clé : renommer une clé dans une définition laisse
les données nommer l'ancienne. Une clé est donc migrée dans les données avant de quitter le catalogue. Retirer un thème
du catalogue est sans danger : les avertissements le conservent, et le code d'affichage se rabat sur sa clé.
