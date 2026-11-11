<!-- phil-dev source-sha256=bd29e4f60a7c3b0b615ec1354ce7408380b7ce250b24f701ceb270c0c59fc67a -->
# Points d'extension

La bibliothèque est du PHP simple : elle ne déclare ni service ni port. Une application l'étend par ce que déclarent ses
propres enums.

| Point | Comment |
|---|---|
| Le comportement d'une enum | Les traits : le trait agrégé, ou les traits individuels dont une enum a besoin — voir [Installation](installation.md) |
| Des métadonnées propres à l'application | Une classe d'attribut qui étend `Phil\Enums\Meta\AbstractMetaProperty`, énumérée dans le `#[Meta(...)]` de l'enum ; elle peut remplacer `transform()`, `defaultValue()` et `customMethodName()` — voir [Métadonnées](metadata.md) |
| Des métadonnées apportées par un trait | Un trait qui porte son propre `#[Meta(...)]` : ses propriétés méta sont lues après celles de l'enum, à l'exécution et par PHPStan |
| Les métadonnées que chaque cas doit avoir | `#[RequiredMeta(...)]` sur l'enum, signalé par la règle PHPStan |
| D'autres noms à partir desquels un cas est analysé | `#[Alias(...)]` sur le cas |
| Le cas donné lorsqu'aucun ne l'est | `#[DefaultCase]` sur un cas |
| Quels cas suivent quels cas | `#[TransitionsTo(...)]` sur chaque cas |
| Le typage pour PHPStan | `extension.neon`, inclus à la main ou automatiquement par `phpstan/extension-installer` — voir [PHPStan](phpstan.md) |

Les propriétés méta fournies — `Label`, `Description`, `Group`, `Weight`, `Alias` — sont finales : une application qui
veut un autre comportement écrit sa propre propriété méta plutôt que de les étendre.
