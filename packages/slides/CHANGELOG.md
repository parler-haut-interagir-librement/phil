# Changelog

All notable changes to `phil/slides` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.1-alpha.

### Changed

- The Node tests resolve `@hotwired/stimulus` to their stub with a synchronous hook given to `module.registerHooks()`,
  on the loading thread, instead of `module.register()`, which Node deprecates (DEP0205).
- Requires the latest minor of each dependency (`twig/twig` 3.31, `symfony/ux-twig-component` 3.8, `phil/markup` 0.3),
  and of each development requirement (`symfony/ux-toolkit` 3.8, `twig/html-extra` 3.31).

## 0.1.0-alpha - 2026-11-18

### Added

- `Slicer`: a UJO document cut into slides at its separator blocks, `delimiter` and `pageBreak`, at its headings up to
  a level, at both, or automatically; `pageStyle` classes and `pageNumber` skips per slide; empty slides dropped; the
  title of each slide; a deck identifier hashed from the blocks; at most 500 slides and 64 levels of nesting.
- The speaker's notes taken out of what the audience sees wherever they are nested: `container` blocks of the notes
  class, and the `note` tune of any block, which `phil/markup` gives the block after an HTML comment; the note of a
  separator, or of a heading that starts a slide, kept with the slide it closes.
- `CommentDirectives`: a note made of Marp directives alone read as directives of its slide — `_class` and
  `_paginate` for the slide, `class` and `paginate` for it and the ones after —, the others listed on the deck; a fuzz
  target, `comment-directives`.
- `Directives`: the global directives `title`, `lang`, `class`, `paginate`, `header`, `footer`, `size` and
  `headingDivider` read from the metadata of a source, those not kept listed on the deck.
- `Deck`, `Slide` with `showsNumber()`, `DeckSettings`, `SplitMode`, `AspectRatio`, `NotesPageSize`, and the
  exceptions `DeckRefused` and `InvalidOption` under `SlidesException`.
- `DeckReader`: decks from a document, its UJO JSON, a reading of `phil/markup`, or a source read by `phil/markup` at
  the configured level, up to a size.
- `SlideRenderer`: each slide, and its notes apart, rendered by the `Ujo` service of `phil/ujo-bundle`.
- The Twig functions `slides_deck`, `slides_markup`, `slides_content`, `slides_notes`, `slides_fragment_class` and
  `slides_kit`.
- The templates `@PhilSlides/deck.html.twig`, `presenter.html.twig`, `presenter_page.html.twig`, `layout.html.twig`,
  `print.html.twig` and `_slide.html.twig`: a deck read in order without script, the notes only in the presenter view;
  every button and link drawn with the recipe `Button` of the PHIL UX kit or of the Shadcn kit, tested with both.
- The presenter routes of `config/routes.php`, imported by the application when it wants them: the presenter view and
  the PDF with the notes, `PresenterController`, for the role `presenter_role` only (`ROLE_SLIDES_PRESENTER` by
  default, 403 for everyone else, before any deck is looked for), never cached; the decks from the application's
  `PresentedDecks`, as a `Presentation`, none by default (`NoPresentedDecks`).
- The Stimulus controller `deck`: one slide at a time, fragments one by one, the keyboard, full screen, the address on
  the current slide, a live region.
- The Stimulus controller `presenter`: the current slide and its notes, the next slide, a timer with the time left.
- Every window of a deck kept at the same position through a `BroadcastChannel`, with no server.
- The stylesheets `slides.css`, over the tokens of PHIL UX, else those of the Shadcn kit, with `isX` and `hasX`
  states, colours mixed in OKLCH, reduced motion respected and one slide per printed page, and `slides-print.css`.
- `DeckPrint`: the body, the `@page` rule of `phil/paged-media` and the document of a printed deck, one page per
  slide, the notes on an upright page on request; `DeckPdf`: its PDF through `phil/paged-media-bundle`.
- The configuration `phil_slides`, with `presenter_role`, `layout` and `kit_subdirectory`; the Markdown level `all` by
  default, raw HTML left to the refusal of `raw` blocks by `phil/ujo-bundle`; the compiler pass `SlidesServicesPass`;
  the assets mapped for AssetMapper.
- The labels in English and French, in the translation domain `phil_slides`.
- The Flex recipe `recipe/0.1`: the bundle, `config/packages/phil_slides.yaml` with the presenter role, and
  `config/routes/phil_slides.yaml` with the import of the presenter routes commented.
- The documentation in `doc/`: installation and the kit, configuration, authoring and notes in HTML comments, showing
  a deck, the presenter view, its routes and their security, PDF export, extension points, testing and reference.
- A benchmark of a deck of two hundred slides cut, read from Markdown and rendered.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
