# Testing

| Area | What is covered |
|---|---|
| `Slicer`, `SlideDraft` | Every split mode and heading level, `headingDivider`, page styles and numbers, empty slides, titles, notes nested anywhere and their tunes, the identifier, the limits of slides and depth, the options |
| `Directives` | Every directive, the values refused and named, the limits |
| `CommentDirectives`, notes from HTML comments | The directives of one slide and of the ones after, those not kept, the notes that stay notes, the notes of a separator or of a heading that starts a slide, the number of a slide; the Markdown comments of a `phil/markup` that keeps them |
| `Deck`, `Slide`, `DeckSettings`, the enums and the exceptions | Lookups, notes, anchors, identifiers, pages, ratios, messages |
| `DeckReader`, `SlideRenderer` | Documents, JSON, readings and sources in several formats and levels, the size limit; content without notes, notes as blocks and texts, the renderer's own rules |
| `SlidesExtension` and the templates | The audience page without any note, the presenter view with them, the print with and without, the options of each, English and French; the names of the recipes of the kit |
| The kits | The audience page and the presenter view drawn with the recipe `Button` of PHIL UX and of Shadcn: the same buttons and links, labels, actions, targets, states, variants and sizes in both, each with its kit's look |
| `PresenterController`, the routes | The role asked for first, a visitor refused (403), someone not signed in asked to sign in (401), everyone refused without an authorization checker, a deck nobody has (404), the configured role, the PDF and its headers, without `phil/paged-media-bundle` |
| The recipe | The manifest, the configuration accepted by the bundle, the routes import left commented |
| `DeckPrint`, `DeckPdf` | The `@page` rules, the body, the whole document, a print engine given the page, the pipeline |
| `PhilSlidesBundle`, `SlidesServicesPass` | The services and the configuration, the refused options, the mapped assets, the pass with and without Twig, a router, `phil/paged-media-bundle` and the `Ujo` service, the subdirectory of the kit |
| The Stimulus controllers | Keys, positions and fragments, the address, full screen, the `BroadcastChannel` messages, disconnecting, the next slide, the timer, the windows opened, under Node's test runner |

A functional suite boots a kernel with FrameworkBundle in `fr_CA` with AssetMapper, TwigBundle, TwigComponentBundle
with the recipe `Button` copied where the Toolkit puts it — from PHIL UX in the subdirectory `ui`, or from the Shadcn
kit at the root of the components in the environment `shadcn` —, SecurityBundle with users over HTTP Basic,
`phil/ujo-bundle`, `phil/paged-media-bundle` with a print engine that hands the document back, and this bundle with
its presenter routes: `SlidesBundleTest` renders both pages in French, prints a deck with and without its notes, and
finds the controllers in AssetMapper; `TemplatesTest` renders the templates with both kits; `PresenterRoutesTest`
requests the presenter routes as the speaker, a visitor and someone not signed in. Installed on its own, where
FrameworkBundle is a development requirement, the suite is skipped.

A fuzz target, `comment-directives`, reads notes nobody wrote as directives and cuts a deck from them: `composer fuzz
comment-directives`.

A benchmark measures a document of two hundred slides cut, the same deck read from Markdown, and every slide rendered
with its notes.

```sh
vendor/bin/phpunit packages/slides/tests
node --import ./tests/js/register.mjs --test tests/js   # from packages/slides
```

In the monorepo, Infection holds the bundle at 100 % of covered mutants.
