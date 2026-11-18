# Extension points

What an application configures, overrides, replaces or relies on. The package is at `0.x`: its extension points are
stable only from its 1.0 release; until then, a minor version may change them, and its CHANGELOG says how.

## Cutting

| Point | How |
|---|---|
| Where slides are cut | `split`, `heading_level`, and the directive `headingDivider` of a source — see [Configuration](configuration.md) |
| What holds notes, what is a fragment | `notes_class`, `fragment_class`; the `note` tune of any block, and the Marp directives of a note, read by `CommentDirectives` |
| The limits | `max_slides`, `max_source_bytes` |
| The formats read | The formats and add-ons of `phil/markup`: `DeckReader` takes the `DefaultCompiler` service when `phil/markup-bundle` registers one |
| Cutting in code | `Slicer::slice()`, or `DeckReader` for documents, JSON, readings and sources |

## Rendering

| Point | How |
|---|---|
| The block types, raw HTML, the limits of a document | The configuration of `phil_ujo`: every slide is rendered by its `Ujo` service |
| A block type of the application's own | Registered with `phil/ujo-bundle`, the same as for every document |
| The markup | `@PhilSlides/deck.html.twig`, `presenter.html.twig`, `presenter_page.html.twig`, `layout.html.twig`, `print.html.twig` and `_slide.html.twig`, overridden in `templates/bundles/PhilSlidesBundle/` |
| The buttons | The recipe `Button` of the kit the application installed, PHIL UX or Shadcn, under the name `slides_kit('Button')` gives; `kit_subdirectory` |
| The labels | The translation domain `phil_slides`: an application's translations override the shipped ones |
| The look | `assets/styles/slides.css`, its `phil-slide*` classes and `isX` states; the classes given by the directive `class` and by `pageStyle` blocks |
| The printed look | `assets/styles/slides-print.css`, or the application's own stylesheets around `DeckPrint::body()` |

## Behaviour

| Point | How |
|---|---|
| The keys | `keyboard: 'page'` on the audience page; the controllers extend one base class, `assets/src/lib/slides_controller.js`, whose `run()` takes a command |
| The windows of a deck | The deck's identifier, given by `Deck::withId()` or the last argument of `slides_deck()` and `slides_markup()` |
| The decks of the presenter routes | A service that implements `Phil\Slides\Application\Contract\PresentedDecks`, aliased to that interface |
| Access to the notes | `presenter_role`, asked for by the presenter routes; or the application's own routes, which serve the presenter view and the PDF with notes to whom it decides |
| The page of the presenter route | `layout`, a template with the blocks `title` and `main`; or `@PhilSlides/presenter_page.html.twig`, overridden |

## Printing

| Point | How |
|---|---|
| The print engine | The `PrintEngine` of `phil/paged-media-bundle` |
| Stages after the print | The `Pipeline` given to `DeckPdf::render()` |
| The page of the notes | `pdf.notes_page_size` |
