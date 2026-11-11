# phil/footnotes-sidenotes

**Footnotes and margin notes for UJO (Universal JSON Output) documents, on screen and in print.**

phil/footnotes-sidenotes works on the notes a UJO document already holds: `footnote` blocks and the
`<sup data-footnote="id">` references of its text. A resolver walks the document once, collects both, numbers the notes
in reading order — restarting per document, per chapter or per page marker, in decimal, roman numerals or symbols, per
locale — and reports what is wrong as typed results: a reference without a note, a note never referenced, a duplicate
identifier, a reference inside a note, a note referenced several times.

The caller then chooses where the notes go. At the end of the document or of each section, as a labelled region with
pairs of links between reference and note. Beside the text, as margin notes: a column on wide viewports, an inline toggle
on narrow ones, with CSS only. At the foot of the printed page, in CSS GCPM, for a print engine that floats footnotes.

It is for anything long enough to need notes: a book, a game rulebook, a documentation page. It is a plain PHP library
on `phil/ujo`, keeps nothing between calls, and ships two small stylesheets that dress semantic HTML with PHIL UX classes.

The text of a note always goes through the inline sanitizer of `phil/ujo`, never raw. Identifiers carry a prefix, so
several documents can share a page.

## Status

| Part | State |
|---|---|
| `NoteResolver`, `Resolution`, `Note`, `NoteReference`, `Problem`, `ProblemKind` | Implemented and tested |
| `NoteOptions`, `Restart`, `Numbering`: decimal, roman, symbols, per locale | Implemented and tested |
| `Html\NotesRenderer`, `Placement`: end of document, end of section, margin, page foot | Implemented and tested |
| `NoteLabels`: English, French, any other from the application | Implemented and tested |
| `Text\NotesPlainText`: references and notes as plain text | Implemented and tested |
| `Stylesheet`: the screen and print sheets, the per-page count of the engine | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, serving the stylesheets. |
| [Resolving notes](doc/resolving.md) | Reading order, restarts, numbering styles and locales, the problems reported. |
| [Placements](doc/placements.md) | The four placements and the HTML each one writes. |
| [Accessibility](doc/accessibility.md) | Link pairs, identifiers, regions, roles, labels, the keyboard and reduced motion. |
| [Styling](doc/styling.md) | The classes, the tokens, the margin column and the narrow toggle. |
| [Print](doc/print.md) | CSS GCPM, labels in the call and the marker, white space, restarts per natural page. |
| [Plain text](doc/plain-text.md) | The form for a search index or an accessibility check. |
| [Extension points](doc/extension-points.md) | What an application chooses, completes or relies on. |
| [Testing](doc/testing.md) | The library's tests, benchmarks, fuzz target and mutation bar. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/footnotes-sidenotes
```

```php
use Phil\FootnotesSidenotes\Html\NotesRenderer;
use Phil\FootnotesSidenotes\NoteLabels;
use Phil\FootnotesSidenotes\NoteOptions;
use Phil\FootnotesSidenotes\NoteResolver;
use Phil\FootnotesSidenotes\Numbering;
use Phil\FootnotesSidenotes\Placement;
use Phil\FootnotesSidenotes\ProblemKind;
use Phil\FootnotesSidenotes\Restart;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Ujo;

$document = new Ujo()->decode($json);

$resolver = new NoteResolver(new NoteOptions(
    restart: Restart::Chapter,
    locales: ['fr' => Numbering::symbols()],
));
$notes = $resolver->resolve($document, 'fr-CA');

foreach ($notes->problemsOf(ProblemKind::MissingNote) as $problem) {
    // $problem->noteId, $problem->blockId
}

$renderer = new NotesRenderer(HtmlRenderer::default(), NoteLabels::forLocale('fr-CA'));

echo $renderer->render($document, $notes, Placement::Margin, idPrefix: 'chapter-3');
```

## Requirements

PHP 8.5 or later with `ext-hash` and `ext-mbstring`, and `phil/ujo`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
