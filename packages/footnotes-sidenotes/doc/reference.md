# Reference

| Type | Kind | Role |
|---|---|---|
| `NoteResolver` | `final readonly class` | Resolves the notes of a document |
| `NoteOptions` | `final readonly class` | Restarts, chapter level, page markers, numbering per locale |
| `Restart` | string-backed enum | Document, chapter or page marker |
| `Numbering` | `final readonly class` | How a number is written |
| `Resolution` | `final readonly class` | The notes, numbered, and the problems |
| `Note` | `final readonly class` | A note the text references |
| `NoteReference` | `final readonly class` | One reference to a note |
| `Problem` | `final readonly class` | One problem of the notes |
| `ProblemKind` | string-backed enum | The kinds of problems |
| `Placement` | string-backed enum | Where notes are written |
| `NoteLabels` | `final readonly class` | The words readers hear or read |
| `Stylesheet` | string-backed enum | The screen and print sheets and the sheet of the per-page count, by path or as text |
| `Html\NotesRenderer` | `final readonly class` | Renders a document with its notes |
| `Text\NotesPlainText` | `final readonly class` | A document with its notes as plain text |
| `Exception\FootnotesException` | interface | Every exception of the library |
| `Exception\InvalidOptions` | `final class`, `InvalidArgumentException` | An option that breaks a rule |

`Internal\*` and the other classes of `Html` are marked `@internal`: they are the working parts of the types above.
