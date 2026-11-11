# Extension points

The library is plain PHP: it declares no service, and its classes are final. What an application chooses lives in the
values it passes in.

| Point | How |
|---|---|
| Where numbering starts again | `NoteOptions::$restart`, `$chapterLevel`, `$pageMarkers` |
| How numbers are written | `Numbering::decimal()`, `lowerRoman()`, `upperRoman()`, `symbols($sequence)` |
| The numbering of a locale | `NoteOptions::$locales`, by locale or language |
| Where notes go | The `Placement` of each `render()` |
| The rendering of the other blocks | The `HtmlRenderer` given to `NotesRenderer`, with its own renderers and filters |
| What a note may hold | The `InlineSanitizer` and the profile given to `NotesRenderer`, `rich` by default |
| The words | `NoteLabels`, built with the application's translations |
| Identifiers | `$idPrefix` of `render()` |
| The look | The application's own rules over the classes, or the tokens the sheets read; see [Styling](styling.md) |
| The numbering of natural pages | The application's print rules; see [Print](print.md) |
| The conversion of other blocks to text | The `PlainText` given to `NotesPlainText` |
| What to do about a problem | The application reads `Resolution::$problems`: refuse, warn or carry on |
| Catching the library's errors | Every exception implements `Exception\FootnotesException` |
