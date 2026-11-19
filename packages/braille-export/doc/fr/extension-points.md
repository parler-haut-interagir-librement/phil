<!-- phil-dev source-sha256=be97f2e3c56b1fdc1dafaf29d698df57a785cda40ce139db53ca2de9b06f4768 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Traduire avec d'autres tables, ou une autre langue | Implémentez `Contract\Translator`, ou utilisez `phil/braille-export-liblouis` |
| Se rabattre sur le braille intégral | `ChainTranslator`, le traducteur PHP en dernier |
| Trouver les caractères d'un texte qui n'ont pas de braille | `new UncontractedTranslator(strict: true)` |
| Changer la taille des pages, les numéros de page, les volumes | `PageFormat`, donné à `BrailleExporter` |
| Donner à un fichier PEF son identifiant, son auteur, son éditeur et sa date | `PefMetadata`, donné à `PefWriter::write()` |
| Changer le texte d'un bloc UJO, ou lire un type qui vous est propre | Un `PlainText` de `phil/ujo` avec `with()`, donné à `UjoReader` |
| Lire un document d'un autre format | Construisez un `Document` de ses blocs |
| Écrire un autre format | Lisez le `Book` : ses volumes, sections, pages et rangées |

`BrailleExporter`, les traducteurs et les écrivains sont `final` : une application les décore, ou écrit les siens sur le
port.
