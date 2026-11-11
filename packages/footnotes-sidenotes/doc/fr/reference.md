<!-- phil-dev source-sha256=1074a4efdbaca2240674cba42c578fcc81c18793a190bf1ef218147529fbef6d -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `NoteResolver` | `final readonly class` | Résout les notes d'un document |
| `NoteOptions` | `final readonly class` | Les redémarrages, le niveau de chapitre, les marqueurs de page, la numérotation par locale |
| `Restart` | enum adossée à des chaînes | Document, chapitre ou marqueur de page |
| `Numbering` | `final readonly class` | La façon d'écrire un numéro |
| `Resolution` | `final readonly class` | Les notes, numérotées, et les problèmes |
| `Note` | `final readonly class` | Une note appelée par le texte |
| `NoteReference` | `final readonly class` | Un renvoi à une note |
| `Problem` | `final readonly class` | Un problème des notes |
| `ProblemKind` | enum adossée à des chaînes | Les genres de problèmes |
| `Placement` | enum adossée à des chaînes | L'endroit où les notes sont écrites |
| `NoteLabels` | `final readonly class` | Les mots que les lecteurs entendent ou lisent |
| `Stylesheet` | enum adossée à des chaînes | Les feuilles pour l'écran et l'impression et la feuille du compte par page, par chemin ou en texte |
| `Html\NotesRenderer` | `final readonly class` | Produit le rendu d'un document avec ses notes |
| `Text\NotesPlainText` | `final readonly class` | Un document avec ses notes en texte brut |
| `Exception\FootnotesException` | interface | Toutes les exceptions de la bibliothèque |
| `Exception\InvalidOptions` | `final class`, `InvalidArgumentException` | Une option qui enfreint une règle |

`Internal\*` et les autres classes de `Html` sont marquées `@internal` : ce sont les rouages des types ci-dessus.
