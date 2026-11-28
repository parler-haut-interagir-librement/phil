<!-- phil-dev source-sha256=ce8cc7b4ddc715e406e65e067e198b8ef25ad0b174130d7e7b6d3fecf134e562 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Ajouter des unités à une famille, ou une nouvelle famille | Passez des définitions à `UnitRegistry::withDefinitions()` : voir [Unités et familles](units.md#ajouter-des-unités-et-des-familles). |
| Remplacer les unités déclarées | Construisez le registre avec `UnitRegistry::fromDefinitions()` à partir de vos propres définitions. |
| Choisir l'unité de chaque canal de vente | Construisez `ChannelConversions` avec vos canaux. |
| Utiliser d'autres préférences d'unités | Construisez `UnitPreferences` avec votre registre et un tableau de la forme de `data/cldr/preferences.php`. |
| Utiliser d'autres motifs d'unités | Construisez `UnitFormatter` avec un répertoire de fichiers de la forme de `data/cldr/patterns/<locale>.php`. |
| Importer une autre version de CLDR ou d'autres locales | Lancez `bin/import-cldr.php` : voir [les données de CLDR](cldr.md#les-données-de-cldr). |
| Traiter toutes les erreurs au même endroit | Interceptez `MeasurementException`. |

`Fraction` et `CldrImporter` sont internes : ils peuvent changer dans n'importe quelle version.
