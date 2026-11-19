<!-- phil-dev source-sha256=be0710215f5400f5b0ec1e45608f9581bc9a53098e32ad18cc694a4fae884bdf -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

Le paquet est un seul adaptateur : `LiblouisTranslator`, le `Phil\BrailleExport\Contract\Translator` de
`phil/braille-export`. Il ne déclare aucun port à lui.

| Pour | Faites |
|---|---|
| L'utiliser | Donnez-le à `BrailleExporter`, seul ou en premier dans un `ChainTranslator` : voir [Brancher le service](installation.md#brancher-le-service) |
| Traduire une autre langue, ou avec d'autres tables | `LiblouisTables`, donné comme `$tables` |
| Utiliser des tables gardées ailleurs | `$tablePath` |
| Pointer vers un autre programme | `$binary` : un nom trouvé dans le `PATH`, ou un chemin |
| Changer le temps que peut prendre un lot, ou sa taille | `$timeout`, `$maxInputBytes`, `$maxOutputBytes` |
| Utiliser une autre table d'affichage qui écrit du braille Unicode | `$displayTable` |
| Vérifier le programme au déploiement | `LiblouisTranslator::isAvailable()` et `version()` |
| Utiliser un autre programme de traduction | Écrivez un autre `Translator` |

`LiblouisTranslator` est `final` : une application le décore comme un service.
