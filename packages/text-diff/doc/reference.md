# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\TextDiff\TextDiff` | `final readonly class` | The library in one object: texts and documents compared, differences as HTML |
| `Phil\TextDiff\Tokenizer` | enum | How a text is cut into tokens: words, lines, paragraphs, HTML |
| `Phil\TextDiff\Diff` | `final readonly class` | The operations of a difference, both texts, the change list |
| `Phil\TextDiff\Operation` | `final readonly class` | A run of tokens kept, deleted or inserted |
| `Phil\TextDiff\OperationKind` | enum | Equal, delete or insert |
| `Phil\TextDiff\Change` | `final readonly class` | One change, its old and new text and its offsets |
| `Phil\TextDiff\ChangeKind` | enum | Insertion, deletion or replacement |
| `Phil\TextDiff\HtmlFormatter` | `final readonly class` | A difference as HTML with `<ins>` and `<del>` |
| `Phil\TextDiff\Myers` | `final readonly class`, internal | Myers' algorithm over two lists of tokens, bounded |
| `Phil\TextDiff\Ujo\DocumentDiff` | `final readonly class` | Every block of two compared documents |
| `Phil\TextDiff\Ujo\BlockChange` | `final readonly class` | One block: what happened to it, where, and its field changes |
| `Phil\TextDiff\Ujo\BlockChangeKind` | enum | Unchanged, changed, added, removed or moved |
| `Phil\TextDiff\Ujo\FieldChange` | `final readonly class` | One value of a block that changed, by path |
| `Phil\TextDiff\Ujo\DocumentDiffer` | `final readonly class`, internal | Blocks paired by identifier, level by level, and compared value by value |
| `Phil\TextDiff\Ujo\DocumentFormatter` | `final readonly class`, internal | A document difference as HTML through `phil/ujo` |
| `Phil\TextDiff\Exception\TextDiffException` | interface | Every exception of the library |
| `Phil\TextDiff\Exception\InvalidText` | `final class` | A text that is not valid UTF-8 |
| `Phil\TextDiff\Exception\InvalidConfiguration` | `final class` | A negative bound of edits |
