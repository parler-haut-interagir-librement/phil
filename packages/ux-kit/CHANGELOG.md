# Changelog

All notable changes to `phil/ux-kit` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.8.0-alpha - 2026-11-19

### Added

- Visual tests: each layout of the images, an image `fixed`, `constrained`, `full-width` and cropped to a ratio, a
  gallery in a grid and a figure of three columns, compared in the browser suite with a reference screenshot kept in
  `tests/Browser/screenshots/`, a few hundred bytes each. `Phil\UxKit\Test\Screenshots` compares, on GD, a screenshot
  shrunk to a scale of its CSS width with its reference, pixel by pixel within a threshold, the share of changed pixels
  within a tolerance; `BrowserTestCase::assertLooksLike()` and `screenshots()`, the directory of a suite's references, a
  missing one written and the test marked incomplete, all of them written again with `PHIL_UX_UPDATE_SCREENSHOTS=1`.
- `doc/images.md`: choosing `layout` and `sizes`, case by case, and the checklist of the largest contentful paint; in
  French in `doc/fr/images.md`.
- `phil:ux-kit:migrate-images`, with the Console component: the `Image` and `Picture` recipes proposed in place of the
  `img` and `picture` elements of the Twig templates of a project, or what stands in the way of each; `--write`
  rewrites the proposals only. `Phil\UxKit\Application\ImageMigration` and `ImageMigrationFinding`.
- `tests/Benchmark/GalleryBench.php`: the rendering of a gallery of 100 images with PHPBench, in a grid, with lightboxes,
  deferred and with the sources of `Picture`.
- `ext-gd`, for the screenshots, and `symfony/console`, for the command, as development requirements, and suggested.

### Changed

- `AspectRatio` writes no `style` attribute: a common ratio is a class, `hasRatio16x9` and the others, any other its
  width and height in `data-ratio-width` and `data-ratio-height`, which phil-ux reads through a typed `attr()`.
- `Badge` writes its `color` in `data-color`, never in a `style` attribute.
- Both hold under a strict Content Security Policy, and are back on the strict pages of the browser suite, which
  measures the ratio and reads the colour.
- The image recipe requires `phil/ux-kit:^0.8@dev`; `manifest.json` requires `phil/ux-css:^0.8@dev`; the branch alias of
  `dev-main` is `0.8.x-dev`.

### Fixed

- `symfony/yaml` is a development requirement: the functional tests of LiipImagine load routes in YAML, and failed when
  the package was installed on its own.
- The dates of 0.3.0-alpha and 0.4.0-alpha, both released on 2026-11-11.

## 0.7.0-alpha - 2026-11-17

### Added

- On `Image` and `Picture`: `frame`, a frame drawn from the tokens of phil-ux, words separated by spaces, `rounded`
  (`hasRounded`), `framed` (`hasFramed`) and `raised` (`hasRaised`), or `token` (`hasToken`), the picture covering a
  square box cropped to a disc inside a ring, for a pawn or an avatar; an unknown word is left out.
- On `Image` and `Picture`: `pixelated`, pixel art enlarged without blurring its pixels (`hasPixelated`).
- On `Image` and `Picture`: `transition`, the name of the image in a view transition, written in `data-transition`
  with the class `hasTransition`, which phil-ux reads into `view-transition-name` through a typed `attr()`, never a
  `style` attribute; a name that is no CSS identifier, or a keyword of `view-transition-name`, is left out. The image
  README shows its use with Turbo 8 and with cross-document view transitions, and its limit: a name stands once in a
  page.
- `Gallery` carries `frame` and `pixelated` to every image, and each image its own `transition`; `Lightbox` carries
  `frame` and `transition` to its thumbnail and `pixelated` to both its images; `Hotspots` carries `frame`, never a
  token, and `pixelated`.
- `RecipesPage` writes each mode under a strict Content Security Policy too, `strict.html`, `strict-dark.html` and
  `strict-rtl.html`: `default-src 'self'`, no `'unsafe-inline'` for scripts or styles, the inline scripts the page
  runs allowed by a nonce of its own, `img-src 'self' data:`, in a `meta` element first in the head, with a watcher,
  `strict.js`, that lists in `window.violations` what the browser refuses; the template reads `strict`; the `page.css`
  of the resources, when there is one, is copied beside the page. `RecipesPage::POLICY` is the policy.
- `BrowserTestCase::strictModes()`, the modes under the strict policy, on which `open()` and the end of a test fail on
  anything the browser refused; `isStrict()`; `isRightToLeft()` knows `strict-rtl.html`.
- The browser suite runs every test of the images and of the celebration under the strict policy too, finds nothing
  refused on any strict page and sees a `style` attribute written afterwards refused; it checks the frames, the token,
  the pixel art and a view transition moving an image under its name, the images on paper, the medium emulated, and the
  images right to left: a caption, a gallery and a carousel starting on the right, the button at the start of a
  carousel pointing to the right, the close button of a lightbox in the top left corner; axe-core audits the frames.
- The image README says how the image behaves under a strict policy and on paper; the celebration README, under a
  strict policy.

### Changed

- The page of the browser suite has no `style` attribute left, its own rules in `tests/Browser/resources/page.css`; a
  badge of a colour of its own and an aspect ratio, which write a custom property in a `style` attribute, are left out
  of the strict pages.
- The image recipe requires `phil/ux-kit:^0.7@dev`; `manifest.json` requires `phil/ux-css:^0.7@dev`; the branch alias of
  `dev-main` is `0.7.x-dev`.

## 0.6.0-alpha - 2026-11-15

### Added

- The recipe `Celebration`: a message said in a polite live region, and confetti or fireworks (`effect`) drawn over the
  page on a canvas by its new Stimulus controller `celebration`, without a library, in the colours of the tokens of
  phil-ux resolved through the CSSOM, so those of the theme; it plays as soon as the page shows it with `autoplay`, on
  the DOM event `celebrate` dispatched anywhere on the page, its `detail` giving the `message` and the `effect` of this
  time, and by the action `celebration#play`, whose params do the same; a block `content` holds what plays it. Where
  motion is reduced, by the system or by `data-motion="reduce"`, only the message shows. No sound; the canvas, cloned
  from a `template`, hidden from screen readers and set over the page through the CSSOM without taking a click, safe
  under a strict Content Security Policy; `duration` kept between 600 and 4000 milliseconds, held while the tab is
  hidden; one animation at a time; fireworks burst twice a second at most; the canvas and its bitmap removed at the end
  or when the element leaves the page; the events `celebration:start` and `celebration:end`.
- The browser suite checks the message said, the canvas shown then removed, nothing drawn under reduced motion, the event
  `celebrate` and the button of the demonstration, and audits the celebration with axe-core in clear, in dark and right
  to left.
- `image_filters()`, when LiipImagine is installed: the `src`, `srcset`, `sources`, `width` and `height` of a `Picture`
  from the filter sets applied to one image, each set a candidate of the width its last resizing filter gives, or of
  the one the template gives; the sets of AVIF and WebP make the sources, the others the fallback, whose widest set
  gives `src`, and the size of the image when it crops exactly. `liip/imagine-bundle` is suggested; `ImageFiltersPass`
  leaves the bridge out when the bundle is not registered.
- `picture_props()` and the interface `Phil\UxKit\Application\Contract\PicturePropsProvider`, which autoconfiguration
  tags `phil_ux_kit.picture_props`: the props of a `Picture` for an object of the application, from the first provider
  that supports it, kept to those that describe the image, a map of options handed to it.
- On `Image` and `Picture`: `structured`, which describes the image as a schema.org `ImageObject` through
  `image_json_ld()` and `phil/structured-data`, suggested: its absolute address, made by Symfony's `UrlHelper` and read
  by `phil/url`, its width and height, caption, credit as `creditText`, license address and creation as
  `digitalSourceType`; added to the graph of the page when a `NodeCollector` gathers one, written otherwise in a JSON-LD
  script of its own before the image, without a nonce.

### Changed

- The image recipe requires `phil/ux-kit:^0.6@dev`; `manifest.json` requires `phil/ux-css:^0.6@dev`; the branch alias of
  `dev-main` is `0.6.x-dev`.

## 0.5.0-alpha - 2026-11-13

### Added

- On `Image` and `Picture`: `captionPosition`, `below` by default, or `overlay`, which lays the caption, its credit, its
  license and its creation over the foot of the image on a veil dark enough for light text whatever the picture, the
  figure of class `hasOverlayCaption`, as wide as its image, a description staying under both; `Image:Figure` takes it.
- On `Gallery`: `layout="carousel"`, its figures in one row that scrolls and snaps without a script, the list of class
  `hasLayoutCarousel` reached by the keyboard through `tabindex="0"`; where the browser draws them, a marker per figure
  by `::scroll-marker`, named by the `data-marker` of its `li`, the text alternative of its image or its rank, and a
  button on each side by `::scroll-button()`, named by `previousLabel` and `nextLabel` through the `data-previous` and
  `data-next` of the list; `captionPosition`, carried to each `Picture`.
- The recipe `Hotspots`: an image of the `image` recipe with points of interest, a list of links named by `label` over
  it, each point placed by the classes `hasHotspotX0` to `hasHotspotX100` and `hasHotspotY0` to `hasHotspotY100`, to
  the nearest five per cent, and by its `data-x` and `data-y`, read exactly by a typed `attr()` where the browser does,
  never by an inline style; a point without words, without an address, with a refused address or without a place left
  out; the caption, credit and license in a figure.
- The recipe `Hero`: an image across the whole box that stays a real image, a `Picture` laid out `full-width` and
  covering, `priority` by default, so loaded at once, fetched first and preloaded through WebLink; `size` (`screen`,
  `large`, `auto`) and `align` (`start`, `center`, `end`); its content over the image on a veil.
- The recipe `Video`: a `video` with the controls of the browser, `preload="none"` by default or `metadata`, never an
  autoplay, its poster checked as an image, its sources and WebVTT tracks (`captions` by default, `subtitles`,
  `descriptions`, `chapters`) checked by `image_origin()`, a `data:` source or track refused, a link to the first source
  for a browser that plays no video; `transcript` and `transcriptLabel`, a disclosure the video is described by; the
  caption, credit, license and creation in a figure.
- The recipe `Figure`: several images in a grid of class `isFigureGroup`, as many columns as the container allows or
  `columns` (`2`, `3`, `4`), under one caption, credit, license and creation.
- `RecipesPage` copies the files of the `media` directory of the resources under `media/` of the web root: the browser
  suite plays a two-second WebM clip with its WebVTT captions.
- The browser suite audits with axe-core, in clear, in dark and right to left, the caption over its image, the gallery
  laid out as a carousel, the image with its points, the hero, the video and the figure of several images; it checks the
  caption on the foot of its image, the row of the carousel scrolled at the arrows, each point at its place and under
  the image on a phone, the hero covered by its image, the video fetching nothing before it is played and playing its
  file, and the figure in its columns.

### Changed

- The image recipe requires `phil/ux-kit:^0.5@dev`; `manifest.json` requires `phil/ux-css:^0.5@dev`; the branch alias of
  `dev-main` is `0.5.x-dev`.

## 0.4.0-alpha - 2026-11-11

### Added

- On `Image` and `Picture`: `deferred`, `fetchpriority="low"` for an image far below the fold, `priority` winning over
  it; `placeholder`, the colour of the box while the image loads, a quiet tone of the tokens (`neutral`, `brand`,
  `red`, `green`, `amber`, `blue`, `violet`) or `custom`, written as the classes `hasPlaceholderBlue` and the others,
  never as a style; `preview`, a tiny raster image inline in base 64, handed with the class `hasPreview` to the new
  Stimulus controller `image-preview` of the recipe, which paints it blurred, in an inline SVG at the ratio of the
  image, behind the image through the CSSOM until it has loaded or failed, safe under a strict Content Security
  Policy; `description` and `descriptionLabel`, a long description in a `details` of class `isDescription` after the
  image and before its caption, the image described by its text through `aria-describedby`, beside a description of
  the page, under the identifier of the image followed by `-description` or one of its own.
- On `Picture` and `Logo`: `forced`, the variant drawn for forced colours, offered first where `forced-colors` is
  active, as a URL, a map of densities or sources of their own types on `Picture`; never preloaded.
- On `Gallery`: `deferred`, its figures off screen skipped with `content-visibility: auto` through the class
  `isDeferred`, each `li` carrying the `data-width` and `data-height` of its crop or of its image, and its images
  fetched last; `placeholder` for every image; `preview`, `description` and `id` per image; `descriptionLabel`.
- On `Lightbox`: `deferred`, `placeholder` and `preview` for the thumbnail; `description` and `descriptionLabel`, the
  description after the thumbnail, outside its button, which it describes.
- On `Logo`: `priority`, `layout`, `aspect`, `fit` and `position`, carried to its `Picture`. On `ComingSoon`:
  `logoWidth`, `logoHeight`, `logoPriority`, `logoLayout`, `logoAspect`, `logoFit` and `logoPosition`, carried to its
  `Logo`, and `illustrationLoading`, carried to its `Illustration`, so that the page can preload its logo and load its
  illustration at once; by default, the page is unchanged.
- `image_check()`, called by `Image`: while `phil_ux_kit.image.check` is on, by default with the debug of the kernel,
  `ImageTextCheck` finds a text alternative that is the name of the file (read by `phil/url`, `@2x`, `@480w` and the
  digest of AssetMapper set aside), a word that only says it is an image or a text that starts by saying so, in English
  and in French, and a missing width or height, and each is logged as a warning on the `phil_ux_kit` channel; the page
  is unchanged.
- The browser suite audits with axe-core the picture with its variant for forced colours, its placeholder and its
  description, the image with its preview and the deferred gallery with a lightbox open; it checks the ratio of the
  figures a deferred gallery skips and its focus ring, the preview painted until its image arrives, and the variant
  shown where colours are forced.

### Changed

- The image recipe copies the `image-preview` controller and requires `symfony/stimulus-bundle` and
  `phil/ux-kit:^0.4@dev`; `Image:Figure` makes a figure for a description alone, and writes its `figcaption` only with
  a caption, a credit, a license or a creation.
- `composer.json` requires `psr/log`, for the warnings of `image_check()`; `manifest.json` requires
  `phil/ux-css:^0.4@dev`; the branch alias of `dev-main` is `0.4.x-dev`.

## 0.3.0-alpha - 2026-11-11

### Added

- `PhilUxKitBundle`, the package being a Symfony bundle: the Twig functions the image recipes call, configured under
  `phil_ux_kit.image` (`hosts`, `allow_localhost`). `image_origin()` says where the image at an address, or every
  candidate of a `srcset`, comes from: `local` for a relative path, a `data:image/` one or the page's own origin,
  `external` for a host of the list or under one, read by `phil/url`, `refused` for any other host, credentials, an IP
  address, `localhost` unless allowed, or any other scheme. `image_sources()` gives the `src`, `srcset`, `sources`,
  `width` and `height` of a `Picture` from the files of one image among the assets of AssetMapper (`logo.avif`,
  `logo@2x.avif`, `logo.webp`, `logo.png`…), with the options `densities`, `widths`, `formats` and `fallbacks`.
- On `Image` and `Picture`: `preload`, the main image preloaded through `preload()` of Symfony WebLink with its
  `imagesrcset`, `imagesizes`, `fetchpriority`, `crossorigin` and `referrerpolicy`, by default with `priority`; a
  `Picture` preloads its first source with its `type`, and nothing with a media query. `credit`, `license` with
  `licenseHref` (`rel="license"`), `creation`, a code of the IPTC Digital Source Type vocabulary or an enum whose value
  is one, and `creationLabel`, written under the caption in `small` elements of classes `isCredit`, `isLicense` and
  `isCreation`, through the new `Image:Figure` component.
- On `Picture` and `Logo`: `dark`, offered where `prefers-color-scheme` is dark, and `still`, the still image of an
  animated one, offered where `prefers-reduced-motion` is reduce, each a URL, a map of densities, or sources of their
  own types on `Picture`, written before the other sources.
- The recipe `Gallery`: a list named by `label` in a `div` of class `isGallery`, its figures in a grid that follows its
  container, `aspect`, `fit` and `sizes` for every image, each opened in a `Lightbox` with `lightbox`.
- The recipe `Lightbox`: a thumbnail in a button that opens a native modal `dialog` by `command="show-modal"` and
  `commandfor`, closed by Escape, its button and `closedby="any"`, its Stimulus controller `lightbox` standing in
  where the browser runs neither; the full image shown whole, the caption under both, the labels as props.
- The browser suite audits the images with axe-core 4.14.0, against the rules of WCAG 2.2 A and AA, in clear, in dark
  and right to left; it opens a lightbox, lays out a gallery and follows the colour scheme and the motion of the
  system.

### Changed

- The image recipe refuses an image of another host unless `phil_ux_kit.image.hosts` lists it, and fetches one of a
  listed host with `referrerpolicy="no-referrer"`; it requires `phil/ux-kit` and `symfony/web-link`. `priority` now
  also preloads the image.
- `manifest.json` requires `phil/ux-css:^0.3@dev`; `composer.json` declares the package a `symfony-bundle`, requires
  `phil/url` and the Symfony components of the bundle, and autoloads `Phil\UxKit\` from `src/`; the branch alias of
  `dev-main` is `0.3.x-dev`.

## 0.2.0-alpha - 2026-11-11

### Added

- The recipe `image`, under the prop names of Symfony UX Image, without a service, JavaScript or inline style: `Image`,
  one `img` of class `isImage`, and `Picture`, its `sources` (`{type, srcset, sizes, media}`, AVIF first) before that
  `img`; `src`, `alt`, `width` and `height` required, an empty `alt` making the image decorative, its `title` and
  `aria-*` attributes then left out; `srcset` as HTML writes it or a map of densities, or of widths with `sizes`;
  `sizes` after `auto,` while the image loads lazily; `loading="lazy"` and `decoding="async"` by default, `priority`
  giving `loading="eager"` and `fetchpriority="high"`; `caption`, a `figure` with its escaped `figcaption`, shown under
  a decorative image too; of the other attributes, only `id`, `class`, `title`, `crossorigin`, `referrerpolicy`,
  `itemprop`, `draggable`, `data-*` and `aria-*`, written with `html_attr()` of `twig/html-extra`, which the recipe
  requires with `twig/extra-bundle`; a `javascript:`, `vbscript:` or `data:` URL other than `data:image/` refused, a
  refused `src` rendering nothing, a refused `srcset` or source left out whole.
- On `Image` and `Picture`: `layout`, `fixed`, `constrained` or `full-width`, which computes `sizes` from `width` when
  none is given (`(min-width: <width>px) <width>px, 100vw` constrained, `100vw` full width; a fixed layout reads its
  maps as densities) and writes `hasLayoutFixed` or `hasLayoutFullWidth`; `aspect` (`16/9`, `4/3`, `3/2`, `1/1`, `21/9`,
  `3/4`, `2/3`, `9/16`, or `custom`, read from `--phil-image-ratio`), `fit` (`cover` by default with a ratio, or
  `contain`) and `position` (`center`, `top`, `bottom`, `left`, `right`), written as classes of phil-ux, never as an
  inline style.
- Art direction on `Picture`: the sources with a `media` query come first, in the order of their first query, each group
  AVIF, then WebP, then the other types, then the sources without a query; each source takes the `width` and `height` of
  its crop; a query holding `<`, `>`, `{`, `}` or `;` leaves its source out, a size that is not a positive integer is
  left out.

### Changed

- `Logo` renders through the `Picture` of the `image` recipe, on which it now depends: its `img` carries `isImage`, a
  refused URL is left out as in `Picture`; `webp` also takes a map of densities, and the new `avif` and `srcset` props
  add an AVIF source offered first and the densities of the fallback, `{theme}` and `{locale}` replaced in each.
- `manifest.json` requires `phil/ux-css:^0.2@dev`; the branch alias of `dev-main` is `0.2.x-dev`.

## 0.1.1-alpha - 2026-11-11

Published by mistake with the content of 0.1.0-alpha; use 0.2.0-alpha.

## 0.1.0-alpha - 2026-11-11

### Added

- `manifest.json` and `icon.svg`: the kit for the Symfony UX Toolkit, depending on `phil/ux-css` (Composer) and
  `@phil/ux-css` (npm, importmap) and on Symfony UX Twig Component.
- The recipe `Logo`: an image with an optional WebP source, `{theme}` and `{locale}` replaced in its sources, linked
  when it has an `href`; `alt` required.
- The recipe `AnimatedHeadline` and its Stimulus controller `animated-headline`: the words of a headline revealed
  through a clip that closes to a caret and opens on the next one; one word revealed once, then still; several played
  through once back to the first, the run fitted within five seconds by `timing()` and `BUDGET` (WCAG 2.2.2); with
  `loop`, cycled, with a pause/play `<button>` whose labels `pauseLabel` and `playLabel` are props, the heading named
  by its hidden copy through `aria-labelledby`; held fully open while hovered, focused, paused or in a hidden
  document; each move ended on `transitionend` or after its duration; still under `prefers-reduced-motion` and
  without JavaScript; read whole by screen readers from a visually hidden copy, the animated words hidden and
  `aria-live="off"`.
- The recipe `Illustration`: a figure with a WebP source and its fallback, one picture chosen among candidates on the
  server, at random or by a seed; caption, sizes, loading.
- The recipe `AutosaveStatus`: the live region and the notice of a form's draft, for the autosave controller of
  `phil/forms-autosave`, its targets and actions; the labels of its buttons as props.
- The recipes `ReactionButton` and `ReactionPicker`, for `phil/reactions-bundle`: a reaction pressed or not, or as plain
  text, with a touch target of 2.75rem; the other reactions in a disclosure of plain buttons, with the targets of the
  picker controller of the bundle, the texts as props, and of the attributes of a button only `name`, `value`, `title`,
  `data-*` and `aria-*`.
- The recipes `Badge`, `Button`, `ButtonGroup`, `Card`, `Empty`, `Popover`, `Tabs`, `Accordion`, `Dialog`, `Sheet`, `Switch`,
  `Tooltip`, `Pagination`, `Carousel`, `Spinner`, `Skeleton`, `Alert`, `Avatar`, `Input`, `Label`, `Select`, `Separator`,
  `Textarea`, `Checkbox`, `Breadcrumb`, `DropdownMenu` and `Table`, under the names, props, blocks and Stimulus
  controllers of the Shadcn kit of the Toolkit, on semantic elements and the classes of phil-ux; every text a prop or a
  block. `Dialog` and `Sheet` on `dialog`, HTML first: `closedby="any"` and the invokers `command` and `commandfor`,
  which the controller `dialog` leaves to the browser where it runs them and stands in for where it does not;
  `Popover`, `Select` and `DropdownMenu` on the popover API,
  `Tooltip` on a manual popover, `Accordion` on `details` and `summary`, `Switch` on `input role="switch"`, `Carousel`
  on native scroll snapping, the keyboard of WAI-ARIA in each; every part writes its class of phil-ux, `data-slot` kept
  beside it.
- The recipe `QrCode`, around the component of `phil/qr-code-bundle`.
- The recipes `AlertDialog`, `AspectRatio`, `Collapsible`, `Field`, `Item`, `Kbd`, `NativeSelect`, `Progress`,
  `RadioGroup`, `ScrollArea`, `Toggle` and `ToggleGroup`, under the names, props and blocks of the Shadcn kit, HTML
  first: `AlertDialog` on `dialog` with the role `alertdialog`, `closedby="closerequest"`, the invokers and the
  controller `dialog`, its cancel button focused; `Collapsible` on `details`; `Kbd`, `NativeSelect` and `Progress` on
  their native elements; `RadioGroup`, `Toggle` and `ToggleGroup` on radio buttons and checkboxes, without a
  controller; `ScrollArea` a region the keyboard reaches when it is named; `AspectRatio` refusing any ratio but a number
  or a quotient.
- The block `ComingSoon`: the logo, an eyebrow and the animated headline in an `hgroup`, paragraphs and a content block
  in the left column, as `header` and `main` landmarks or not; an illustration filling the right column with a slanted
  entrance; stacked on small screens.
- The blocks `LoginForm` and `SignupForm`, of plain form elements in a card: the field names of the form login of
  Symfony Security, the last user name written back, the error a live alert the fields name, the CSRF token, the links
  to a forgotten password and to a sign-up; an optional name, the email, the new password with its rule and its
  repetition, the errors of each field tied to it, a password never written back; every word a prop.
- `.gitattributes`: the archive `ux:install` downloads holds the recipes and `src/`, the package's other files left out.
- The recipes `ColorPicker`, `DatePicker`, `CopyButton`, `AnimatedButton`, `Stepper`, `ActivityCalendar`, `Tree`, `Rating`,
  `NumberInput`, `FileInput`, `Tag`, `RelativeTime`, `ProgressRing`, `Sparkline` and `BeforeAfter`, of this kit alone, with
  the controllers they need; every part with its class of phil-ux, under `prefers-reduced-motion`, right to left, at
  375 pixels, and at the keyboard of WAI-ARIA or of the native control they stand on; each works, or shows what it can,
  without a script.
- The recipes `Chart` and `ChartJs`, of this kit alone. `Chart` draws bars or lines in SVG on the server, on a scale of
  four to six plain steps whatever the values, a missing value breaking the line, the last 366 labels and the first
  six series kept, each series in a tone of phil-ux, its line from the second on with a dash of its own, and a
  legend; `ChartJs` puts the canvas of Symfony UX Chart.js in the same figure. The drawing hidden, both write the
  table of their values, read by screen readers or shown in a disclosure.
- `Tooltip`: the controller writes on the bubble the side it went to, `isTop`, `isBottom`, `isStart` or `isEnd`, for
  phil-ux to draw the arrow, and keeps the arrow on the trigger when the bubble is pushed back in the viewport.
- The suite `tests/Browser`, on the real `@hotwired/stimulus` pinned in its own `package.json`.
- `Phil\UxKit\Test\BrowserTestCase` and `Phil\UxKit\Test\RecipesPage` in `src/Test/`, autoloaded: the headless Chrome
  and the page of every recipe in clear, in dark and right to left, for the browser suites of the kits built on the
  same controllers; the build of Stimulus is the suite's own; `clickBeside()`, a real click on the backdrop beside an
  element.
