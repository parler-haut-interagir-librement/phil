# Reference

Every type of the bundle, with its role.

| Type | Role |
|---|---|
| `PhilSlidesBundle` | The bundle: the configuration `phil_slides`, the services, the assets mapped under `@phil/slides`, `DEFAULT_PRESENTER_ROLE` |
| `Domain\Slicer` | Cuts a document into a deck: separators, headings, page styles and numbers, notes taken out, limits |
| `Domain\Directives` | The global directives of a deck read from the metadata of its source, and those not kept |
| `Domain\CommentDirectives` | The Marp directives of one slide written in a `note` tune — an HTML comment —, and those not kept |
| `Domain\SplitMode` | Where a document is cut: `auto`, `separators`, `headings`, `both` |
| `Domain\AspectRatio` | The shape of a slide, `16:9` or `4:3`, its printed page and its CSS ratio |
| `Domain\NotesPageSize` | The upright page of a slide printed with its notes, `letter` or `a4` |
| `Domain\SlideDraft` | Internal: the slide the slicer is filling |
| `Domain\ValueObject\Deck` | The slides of a deck, its settings, its identifier, the directives not kept |
| `Domain\ValueObject\Slide` | One slide: its number, its content, its notes, its classes, its pagination, its title; `showsNumber()` |
| `Domain\ValueObject\DeckSettings` | What applies to every slide: title, language, classes, pagination, header, footer, shape |
| `Domain\Exception\SlidesException` | The interface of every exception of the bundle |
| `Domain\Exception\DeckRefused` | Too many slides, nesting too deep, a source too large |
| `Domain\Exception\InvalidOption` | An option out of its range: a heading level, a limit, a class, an identifier |
| `Application\DeckReader` | Decks from a document, its JSON, a reading of `phil/markup` or a source |
| `Application\SlideRenderer` | The HTML of a slide without its notes, and of its notes apart, by the `Ujo` service |
| `Application\Contract\PresentedDecks` | The decks the presenter routes show, found by identifier: the application implements it |
| `Application\Presentation` | A deck as the presenter route shows it: the deck, the address of its audience page, the length of the talk |
| `Application\NoPresentedDecks` | The decks until the application names its own: none |
| `Infrastructure\Http\PresenterController` | The presenter routes: the presenter view and the PDF with the notes, for the configured role only |
| `Infrastructure\Twig\SlidesExtension` | The Twig functions `slides_deck`, `slides_markup`, `slides_content`, `slides_notes`, `slides_fragment_class`, `slides_kit` |
| `Infrastructure\Print\DeckPrint` | The body, the stylesheets and the document of a printed deck |
| `Infrastructure\Print\DeckPdf` | The PDF of a deck through `phil/paged-media-bundle` |
| `Infrastructure\DependencyInjection\SlidesServicesPass` | Stops the container without the `Ujo` service; removes the print and the presenter routes without Twig, the PDF without `phil/paged-media-bundle`, the presenter routes without a router; names the recipes of the kit after the Toolkit's subdirectory |

## Twig functions

| Function | Gives |
|---|---|
| `slides_deck(document, directives = {}, id = null)` | The deck of a `Document`, its UJO JSON, or a `Reading` of `phil/markup` |
| `slides_markup(source, format = 'markdown', id = null)` | The deck of a source read by `phil/markup` |
| `slides_content(slide)` | The HTML the audience sees of a slide |
| `slides_notes(slide)` | The HTML of a slide's notes, empty without any |
| `slides_fragment_class()` | The class of the fragments, from the configuration |
| `slides_kit(recipe)` | The name a recipe of the kit is installed under: `ui:Button` with the components subdirectory `ui` |

## Routes

`config/routes.php`, imported by the application when it wants them (see [Presenter view](presenter.md)):

| Route | Path | Controller |
|---|---|---|
| `phil_slides_presenter` | `/{deck}` | `PresenterController::view` |
| `phil_slides_presenter_notes_pdf` | `/{deck}/notes.pdf` | `PresenterController::notesPdf` |

## Templates

| Template | What it draws |
|---|---|
| `@PhilSlides/deck.html.twig` | The audience page, with the controller `phil--slides--deck` |
| `@PhilSlides/presenter.html.twig` | The presenter view, with the controller `phil--slides--presenter` |
| `@PhilSlides/presenter_page.html.twig` | The page of the presenter route: the presenter view in the layout `phil_slides.layout` |
| `@PhilSlides/layout.html.twig` | The bare page the presenter route extends when the application names no layout |
| `@PhilSlides/print.html.twig` | The body of a printed deck |
| `@PhilSlides/_slide.html.twig` | One slide, shared by the three |

## Assets

| File | Role |
|---|---|
| `assets/src/deck_controller.js` | The audience controller |
| `assets/src/presenter_controller.js` | The presenter controller |
| `assets/src/lib/slides_controller.js` | Their base: position, keys, live region, `BroadcastChannel` |
| `assets/src/lib/deck.js` | The logic without the DOM: positions, keys, messages, clocks |
| `assets/styles/slides.css` | The look on screen and printed from the browser |
| `assets/styles/slides-print.css` | The layout of a deck printed by `DeckPrint` |
