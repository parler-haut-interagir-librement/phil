<!-- phil-dev source-sha256=fc3a45efbd6d335bb471fdc4348749abe0be27065d8cfb2987f88d9b228557df -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `Phil\Epub\Book` | `final readonly class` | Un livre : métadonnées, chapitres, ressources, couverture, table des matières, libellés, association des adresses, mise en page, narration, table des matières EPUB 2 |
| `Phil\Epub\Metadata` | `final readonly class` | Titre, langue, identifiants, date de modification, créateurs et les autres métadonnées |
| `Phil\Epub\Identifier` | `final readonly class` | Un identifiant UUID, ISBN-13, DOI, URN ou propriétaire |
| `Phil\Epub\IdentifierType` | enum | Le schéma d'un identifiant et son code ONIX |
| `Phil\Epub\Creator` | `final readonly class` | Un créateur ou un collaborateur, son rôle MARC et sa forme de tri |
| `Phil\Epub\Direction` | enum | Par défaut, de gauche à droite, de droite à gauche |
| `Phil\Epub\Chapter` | `final readonly class` | Un document de contenu tiré d'un fragment de HTML, avec sa superposition de médias et sa mise en page |
| `Phil\Epub\LandmarkType` | enum | Le rôle structurel d'un chapitre |
| `Phil\Epub\Asset` | `final readonly class` | Une feuille de style, une image, une police, un fichier audio ou vidéo ou une piste de texte, son type de média, obscurci ou non, sa solution de repli |
| `Phil\Epub\AssetKind` | enum | Feuille de style, image, police, audio, vidéo, piste de texte |
| `Phil\Epub\MediaOverlay` | `final readonly class` | La narration d'un chapitre, ses extraits dans l'ordre de lecture |
| `Phil\Epub\Clip` | `final readonly class` | Un élément d'un chapitre et la partie d'un fichier audio qui le lit |
| `Phil\Epub\Narration` | `final readonly class` | Les narrateurs et les classes des superpositions de médias |
| `Phil\Epub\Rendition` | `final readonly class` | La mise en page du livre, sa fenêtre d'affichage, son orientation et sa double page |
| `Phil\Epub\Layout` | enum | Fluide, prépaginée |
| `Phil\Epub\Viewport` | `final readonly class` | La taille d'une page à mise en page fixe |
| `Phil\Epub\Orientation` | enum | Automatique, paysage, portrait |
| `Phil\Epub\Spread` | enum | Quand deux pages sont affichées côte à côte : automatique, aucune, paysage, toujours |
| `Phil\Epub\PageSpread` | enum | Le côté d'une double page qu'occupe un chapitre : gauche, droite, centre |
| `Phil\Epub\Cover` | `final readonly class` | L'image de couverture et son texte de remplacement |
| `Phil\Epub\TocEntry` | `final readonly class` | Une entrée d'une table des matières donnée |
| `Phil\Epub\NavigationLabels` | `final readonly class` | Les mots du document de navigation |
| `Phil\Epub\HtmlSplitter` | `final readonly class` | Coupe un fragment de HTML en chapitres à ses titres |
| `Phil\Epub\EpubWriter` | `final readonly class` | Écrit un livre dans un fichier ou dans une chaîne |
| `Phil\Epub\Accessibility\Accessibility` | `final readonly class` | Les déclarations d'accessibilité de l'éditeur |
| `Phil\Epub\Accessibility\AccessMode` | enum | Les modes d'accès de schema.org |
| `Phil\Epub\Accessibility\AccessibilityFeature` | enum | Les fonctionnalités d'accessibilité de schema.org |
| `Phil\Epub\Accessibility\AccessibilityHazard` | enum | Les dangers d'accessibilité de schema.org |
| `Phil\Epub\Ujo\UjoChapters` | `final readonly class` | Les chapitres d'un document UJO, rendus par `phil/ujo` |
| `Phil\Epub\Exception\EpubException` | interface | Implémentée par chaque exception de la bibliothèque |
| `Phil\Epub\Exception\InvalidBook` | `final class` | Un livre qui ne peut pas être écrit tel qu'il est décrit |
| `Phil\Epub\Exception\WriteFailed` | `final class` | Une archive qui ne peut pas être écrite |

Les classes sous `Phil\Epub\Internal`, et `Phil\Epub\Ujo\PageMarker`, sont marquées `@internal` : l'assembleur, la
conversion XHTML, les documents de paquet et de navigation, la table des matières EPUB 2, les documents SMIL, les chemins et
l'obscurcissement des polices. Elles peuvent
changer sans préavis.
