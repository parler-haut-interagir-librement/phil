<!-- phil-dev source-sha256=90653a52216fb5e06f26e3c96ca662796fa66e0eb8977d92223d8c6c530a2e0e -->
# Référence

Chaque type public du paquet, avec son rôle. Les classes des espaces de noms `Internal` n'en font pas partie.

| Type | Rôle |
|---|---|
| `BrailleExporter` | Met un document en pages braille : un lot au traducteur, puis lignes, pages, sections et volumes. |
| `Contraction` | `Uncontracted`, degré 1, ou `Contracted`, degré 2. |
| `Contract\Translator` | Le port : des textes en braille Unicode, selon la langue et la contraction. |
| `Contract\Block` | Un bloc d'un document. |
| `Document\Document` | Un document : ses blocs, sa langue et son titre. |
| `Document\Heading` | Un titre de niveau 1 à 6. |
| `Document\Paragraph` | Un paragraphe. |
| `Document\ListBlock`, `Document\ListItem` | Une liste et ses éléments, imbriqués. |
| `Document\Table` | Un tableau, linéarisé par la mise en page. |
| `Document\PageBreak`, `Document\SectionBreak` | Une nouvelle page, une nouvelle section. |
| `Document\PrintPage` | Où commence une page de l'édition imprimée. |
| `Translation\UncontractedTranslator` | Le français et l'anglais intégraux, en PHP. |
| `Translation\ChainTranslator` | Le premier de plusieurs traducteurs qui prend en charge la langue. |
| `Translation\Braille` | Les fonctions utilitaires du braille Unicode : une cellule de points, une vérification, une longueur. |
| `Layout\PageFormat` | La taille d'une page, ses numéros de page, volumes, duplex et la limite de pages. |
| `Book\Book`, `Book\Volume`, `Book\Section`, `Book\Page` | Un livre mis en page, ses volumes, sections, pages et rangées. |
| `Output\BrfWriter` | Un livre en fichier BRF. |
| `Output\PefWriter`, `Output\PefMetadata` | Un livre en fichier PEF 2008-1, et ses métadonnées. |
| `Output\UnicodeWriter` | Un livre en texte braille Unicode. |
| `Ujo\UjoReader` | Un document UJO lu comme un document. |
| `Exception\BrailleExportException` | Chaque exception du paquet. |
| `Exception\TranslationFailed` | Une traduction qui n'a pu se faire. |
| `Exception\UnsupportedTranslation`, `Exception\UntranslatableCharacter`, `Exception\InvalidBraille`, `Exception\DocumentTooLong`, `Exception\UnrepresentableCell` | Voir [Exceptions](exceptions.md). |
