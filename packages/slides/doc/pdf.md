# PDF export

A printed deck is one page per slide, every fragment shown. The notes are left out unless asked for; with them, each
slide is printed on an upright page, the slide above its notes.

## The PDF

With `phil/paged-media-bundle` installed and a print engine configured, `DeckPdf` renders a deck through
`PagedMedia::printHtml()`:

```php
use Phil\Slides\Infrastructure\Print\DeckPdf;

$output = $deckPdf->render($deck);                                // the slides only
$output = $deckPdf->render($deck, withNotes: true, filename: 'talk-notes.pdf');
$output = $deckPdf->render($deck, pipeline: new Pipeline(…));     // then PDF/A, a signature… through Gotenberg

return new Response($output->contents, 200, ['Content-Type' => $output->contentType]);
```

The print announces itself as any print of `phil/paged-media-bundle`, and the pipeline's stages run after it. Without
that bundle, the service `DeckPdf` is removed.

The PDF with the notes is also served, to the role `phil_slides.presenter_role` only, by the presenter route
`phil_slides_presenter_notes_pdf` (see [Presenter view](presenter.md)); the presenter view links to it.

## The page for any engine

`DeckPrint`, registered with TwigBundle, gives what an engine lays out:

| Method | Gives |
|---|---|
| `body($deck, $withNotes)` | The body, from `@PhilSlides/print.html.twig`, each slide rendered by `phil/ujo-bundle` |
| `stylesheets($deck, $withNotes)` | The `@page` rule written by `phil/paged-media`, then `assets/styles/slides-print.css` |
| `document($deck, $withNotes)` | The whole HTML document, its title and language from the directives |
| `language($deck)` | The language of the deck, `en` when its source names none |

| Pages | Size |
|---|---|
| Slides, 16:9 | 960 × 540 points, no margin |
| Slides, 4:3 | 720 × 540 points, no margin |
| With the notes | US Letter or A4 upright, by `pdf.notes_page_size`, half-inch margins |

## Printing from the browser

The stylesheet of the audience page prints one slide per page too, every fragment shown, without the controls; the
presenter view prints the same, without its notes.
