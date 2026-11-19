<!-- phil-dev source-sha256=1b9a99b461038039018d34031bb00dee709df0484f80f261e2616c2dcfcbcc51 -->
# Exceptions

Le bundle n'a aucune exception qui lui soit propre.

| Quand | Ce qui est levé |
|---|---|
| Le conteneur est construit avec une valeur que la configuration refuse, ou avec `media.store: library` sans `PhilMediaBundle` | `Symfony\Component\Config\Definition\Exception\InvalidConfigurationException` |
| `DocumentImports` reçoit un fichier dont le format n'est pas dans `formats.import`, ou un document binaire Word 97–2003 | `Phil\DocumentImport\Exception\UnsupportedFormat` |
| `DocumentImports` reçoit un fichier ou des octets plus grands que `limits.total_bytes`, quel que soit leur format | `Phil\DocumentImport\Exception\LimitExceeded` |
| `DocumentImports::export()` reçoit un format qui n'est pas dans `formats.export` | `Phil\DocumentImport\Exception\ExportFailed` |
| Un fichier est illisible, n'est pas ce que dit son format, ne peut pas être détecté ou dépasse les limites | Les exceptions de `phil/document-import`, toutes des `Phil\DocumentImport\Exception\DocumentImportException` |
| La médiathèque refuse une image, ou le fichier temporaire d'un téléversement ne peut pas être écrit | `Phil\MediaBundle\Domain\Exception\InvalidMedia` |

L'assainissement d'une image SVG ne lève jamais d'exception : une image que l'assainisseur refuse en entier est laissée
de côté et signalée. Un écouteur de `DocumentImported` qui lève une exception arrête l'importation à ce point ; son
exception parvient à l'appelant.
