# Changelog

All notable changes to `phil/ux-kit` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.4.0-alpha - 2026-11-13

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

## 0.3.0-alpha - 2026-11-13

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
