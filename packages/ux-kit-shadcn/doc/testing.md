# Testing

| Area | What is covered |
|---|---|
| The kit | The kit's manifest and icon, every directory a recipe or left out of the archive, each recipe's manifest, README and copied files, the recipes it depends on, the controllers its templates name, every prop documented as the Toolkit asks |
| The parity | The same recipes and manifests as the PHIL UX kit, the same props, blocks and Stimulus attributes, the same controller file; the `button` recipe of the Shadcn kit, which the PHIL UX kit's `Carousel` depends on, drawn inline here; against the PHIL UX kit installed among the development requirements |
| The installation | `ux:install` run by the Toolkit on the archive of the kit, with the components and controllers in the subdirectory `ui`: the carousel's files under it, its identifiers prefixed in markup and in a Twig hash, those of a bundle's controller left as they are |
| The recipes | Each rendered through TwigComponent with its utilities: a logo by theme and by density through its picture, its `alt` required, a headline's words and states, an illustration by seed, an image and a picture with its AVIF and WebP sources, by density and by width, decorative, captioned, laid out, with a ratio, a fit and a crop, art-directed, `priority`, its attributes sifted and its refused URLs, its dark and still variants, its credit, license and creation, an image of another host refused and one of a listed host fetched without a referrer, a structured image and picture described in JSON-LD before them, a gallery on a container query, a lightbox on the native dialog, a deferred image with its placeholder and its preview, a variant for forced colours, a long description, a deferred gallery, a caption over its image, a gallery laid out as a carousel, an image with its points, a hero, a video with its tracks and transcript, several images under one caption, the frames, the token, pixel art and the name of a view transition, carried by a gallery, a lightbox and an image with points, never a token there, the utilities of paper and of right to left, never a style, the status of a draft and its buttons, the whole coming soon page, the series of a chart in the chart tokens and their dashes, the errors of the sign-in and sign-up blocks, a carousel and its buttons in each variant and size of the kit's button, its pause button when it plays; no class of phil-ux in any of them |

The controller's own tests are those of the PHIL UX kit, the file being the same.

```sh
vendor/bin/phpunit packages/ux-kit-shadcn/tests/Unit packages/ux-kit-shadcn/tests/Functional
```

## In a browser

`tests/Browser` drives the recipes a user acts on in a real Chrome with `symfony/panther`, against a stylesheet compiled
with Tailwind, in clear, in dark and right to left: `AnimatedHeadline`, `Tree`, a `Carousel` scrolled one slide at its button and its
arrow key, its buttons disabled at either end, `NumberInput`,
`ColorPicker`, `Tag`, `FileInput`, `BeforeAfter`, `Rating`, `RelativeTime`, `CopyButton`, a `Celebration` played by its
button and by the DOM event, its message said, its canvas over the page above it and letting the pointer through, drawn
on and gone, nothing drawn under reduced motion, audited by axe-core while it plays, the colours and dashes of the
series of a `Chart`, a deferred gallery whose figures off screen keep their ratio, the preview of an image painted until
it arrives, the images of 0.5, audited by axe-core against the rules of WCAG 2.2 A and AA in each mode, the caption on
the foot of its image, the carousel in one row scrolled at the arrows and its buttons named, each point at its place,
the hero covered by its image, the video at its ratio and the figure in its columns, the frames, the token, the pixel
art and a view transition moving an image under its name, the images on paper, the medium emulated, and right to left, a
caption, a gallery and a carousel starting on the right, the button at the start of the carousel pointing to the right
and the close button of a lightbox in the top left corner, and the recipes and blocks at the width of a phone. Every
test of the images and of the celebration also runs under the strict Content Security Policy of `RecipesPage`, on the
pages `strict.html`, `strict-dark.html` and `strict-rtl.html`, and fails at the first thing the browser refuses; a test
finds nothing refused while the recipes render, then sees a `style` attribute refused. The markup and its utilities are
this kit's own while the controllers are shared, so the suite checks what the two do together: what a compiled utility
hides, shows, cuts or paints once a controller has changed the page.

`TailwindStylesheet` compiles `tests/Browser/resources/app.css`, which holds tokens of the names the recipes read. The
page and the browser come from `Phil\UxKit\Test\BrowserTestCase` and `Phil\UxKit\Test\RecipesPage`, which the PHIL
UX kit publishes in its `src/Test/`. The media of the page, a two-second WebM clip and its WebVTT captions, are in
`tests/Browser/resources/media`. Tailwind, tw-animate-css, `@hotwired/stimulus` and axe-core are pinned in
`tests/Browser/package.json` and its lock file: development dependencies of these tests, outside the archive of the kit.

Each layout of the images is compared with a reference screenshot of `tests/Browser/screenshots/`, by
`BrowserTestCase::assertLooksLike()` of the PHIL UX kit: an image `fixed`, `constrained`, `full-width` and cropped to a
ratio, a gallery in a grid and a figure of three columns, in flat colours without text, in a box `w-full max-w-[480px]`,
within the tolerance of `Phil\UxKit\Test\Screenshots`. The references are this kit's own, its utilities drawing the
layouts; after a deliberate change, `PHIL_UX_UPDATE_SCREENSHOTS=1` writes them again, to review in the diff. The
comparison uses GD, `ext-gd`, which the root of the monorepo requires.

The suite is apart from the others, in `phpunit.browser.dist.xml`, and needs Chrome and the `chromedriver` of the system:

```sh
composer test:browser
```

## The linter

The Toolkit's linter, `vendor/bin/ux-toolkit-kit-lint`, run on a copy of the recipes alone, reports no error. Its
warnings concern the dependencies a manifest declares, and the manifests are those of the PHIL UX kit.
