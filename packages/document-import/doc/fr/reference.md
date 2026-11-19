<!-- phil-dev source-sha256=87dac552c8a270fe20aaabd05ef63f2ed1c2fcad7ae0193c3d767b20006dc208 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `Phil\DocumentImport\DocumentImporter` | `final readonly class` | Importe un fichier ou son contenu en document UJO |
| `Phil\DocumentImport\DocumentExporter` | `final readonly class` | Exporte un document UJO en fichier DOCX ou ODT |
| `Phil\DocumentImport\Exported` | `final readonly class` | Le format, les octets et le rapport d'un export |
| `Phil\DocumentImport\Format` | enum | DOCX, ODT, ODT plat, RTF, PDF, Markdown, HTML ; connu d'après un nom ou détecté d'après des octets |
| `Phil\DocumentImport\Imported` | `final readonly class` | Le format, le document, les métadonnées, les médias et le rapport d'une importation ; le document avec son rapport |
| `Phil\DocumentImport\StyleMap` | `final readonly class` | Les styles de paragraphe de l'application et les blocs qu'ils deviennent |
| `Phil\DocumentImport\StyleRole` | enum | Paragraphe, titre d'un niveau, citation, code, ou laissé de côté |
| `Phil\DocumentImport\Contract\MediaSource` | interface | Répond les octets d'une image qu'écrit un export |
| `Phil\DocumentImport\MediaMap` | `final readonly class` | Une source de médias sur des images en mémoire |
| `Phil\DocumentImport\Media` | `final readonly class` | Une image tirée du fichier : chemin, octets, texte de remplacement, type de média |
| `Phil\DocumentImport\MediaStore` | interface | Répond l'adresse d'un bloc d'image |
| `Phil\DocumentImport\MediaFolder` | `final readonly class` | Le magasin par défaut : un préfixe et le nom du fichier |
| `Phil\DocumentImport\Report` | `final class` | Ce qu'une importation ou un export a laissé de côté, en lignes, en phrases ou en un bloc |
| `Phil\DocumentImport\Omission` | `final readonly class` | Une sorte et un détail laissés de côté, et leur nombre |
| `Phil\DocumentImport\Omitted` | enum | Les sortes de choses laissées de côté, et leur description |
| `Phil\DocumentImport\Limits` | `final readonly class` | La plus grande entrée, le plus grand fichier et le plus grand flux, l'imbrication la plus profonde, le plus grand nombre d'objets PDF, le plus grand nombre d'opérations pour lire un PDF |
| `Phil\DocumentImport\Exception\DocumentImportException` | interface | Implémentée par chaque exception de la bibliothèque |
| `Phil\DocumentImport\Exception\InvalidDocument` | `final class` | Un fichier qui n'est pas ce que dit son format |
| `Phil\DocumentImport\Exception\UnsupportedFormat` | `final class` | Un format ni donné ni détecté |
| `Phil\DocumentImport\Exception\LimitExceeded` | `final class` | Un document plus grand, ou imbriqué plus profondément, que les limites |
| `Phil\DocumentImport\Exception\ExportFailed` | `final class` | Un export dans un autre format, ou une archive qui ne peut pas être écrite |

Les classes sous `Phil\DocumentImport\Internal` sont marquées `@internal` : l'archive, le constructeur de blocs, le
rédacteur du texte en ligne, les lecteurs DOCX, ODT, RTF et PDF et leurs parties, les rédacteurs DOCX et ODT, la préparation du HTML. Elles peuvent
changer sans préavis.
