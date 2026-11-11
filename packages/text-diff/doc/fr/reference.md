<!-- phil-dev source-sha256=d68f50df60ed5d1f46ee9f988674b59c64b076f13855ac90bd5f9b9cff9fc9bb -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `Phil\TextDiff\TextDiff` | `final readonly class` | La bibliothèque en un seul objet : textes et documents comparés, différences en HTML |
| `Phil\TextDiff\Tokenizer` | énumération | La façon de découper un texte en jetons : mots, lignes, paragraphes, HTML |
| `Phil\TextDiff\Diff` | `final readonly class` | Les opérations d'une différence, les deux textes, la liste de changements |
| `Phil\TextDiff\Operation` | `final readonly class` | Une suite de jetons conservés, supprimés ou insérés |
| `Phil\TextDiff\OperationKind` | énumération | Égal, suppression ou insertion |
| `Phil\TextDiff\Change` | `final readonly class` | Un changement, son ancien et son nouveau texte et ses positions |
| `Phil\TextDiff\ChangeKind` | énumération | Insertion, suppression ou remplacement |
| `Phil\TextDiff\HtmlFormatter` | `final readonly class` | Une différence en HTML avec `<ins>` et `<del>` |
| `Phil\TextDiff\Myers` | `final readonly class`, interne | L'algorithme de Myers sur deux listes de jetons, borné |
| `Phil\TextDiff\Ujo\DocumentDiff` | `final readonly class` | Chaque bloc de deux documents comparés |
| `Phil\TextDiff\Ujo\BlockChange` | `final readonly class` | Un bloc : ce qui lui est arrivé, où, et ses changements de champs |
| `Phil\TextDiff\Ujo\BlockChangeKind` | énumération | Inchangé, modifié, ajouté, retiré ou déplacé |
| `Phil\TextDiff\Ujo\FieldChange` | `final readonly class` | Une valeur d'un bloc qui a changé, par chemin |
| `Phil\TextDiff\Ujo\DocumentDiffer` | `final readonly class`, interne | Les blocs appariés par identifiant, niveau par niveau, et comparés valeur par valeur |
| `Phil\TextDiff\Ujo\DocumentFormatter` | `final readonly class`, interne | La différence de deux documents en HTML par `phil/ujo` |
| `Phil\TextDiff\Exception\TextDiffException` | interface | Chaque exception de la bibliothèque |
| `Phil\TextDiff\Exception\InvalidText` | `final class` | Un texte qui n'est pas de l'UTF-8 valide |
| `Phil\TextDiff\Exception\InvalidConfiguration` | `final class` | Une borne de modifications négative |
