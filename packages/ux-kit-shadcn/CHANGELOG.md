# Changelog

All notable changes to `phil/ux-kit-shadcn` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.3.1-alpha.

### Added

- As in the PHIL UX kit, the recipes `SeasonalDecor`, a decor for the holidays with its poppy, its egg hunt and its
  secret code, the holiday of the day from `seasonal_decor()` of phil/ux-kit; `Podium`, the first three rising on
  their steps; and `ScratchCard`, a prize under a cover scratched away, its cover in the `--muted-foreground` token. Their
  controllers, `seasonal_decor_controller.js` and `seasonal_decor_shapes.js`, `podium_controller.js` and
  `scratch_card_controller.js`, are those of the PHIL UX kit.
- In the README of `Celebration`, as in the PHIL UX kit, the examples a demo renders: a gallery, one button for each of
  the effects, nothing playing until one is pressed; the button and the words in turn as live examples too.
- As in the PHIL UX kit, eight effects of `Celebration`, `wave`, `rocket`, `rainbow`, `drum-roll`, `applause`,
  `reactions`, `shapes` and `mascot`, its props `reaction`, `shapes`, `mascot` and `vibrate`, and its sounds, silent until
  the person turns them on by the button of `sounds`, drawn in Tailwind, `aria-pressed` while they are on. Its
  controller and the modules beside it, `celebration_sounds.js` among them, are those of the PHIL UX kit.

### Changed

- The `effect` of `Celebration` names its eleven values in its type, as in the PHIL UX kit.

## 0.3.0-alpha - 2026-11-25

### Added

- On `Celebration`, as in the PHIL UX kit: `messages`, words said in turn; `focus`, what the spotlight lights and where
  the trophy lands; the nine effects `lasers`, `balloons`, `shooting-stars`, `streamers`, `coins`, `bubbles`, `aurora`,
  `spotlight` and `trophy`; the choice of each person, `data-celebrations`, and the fatigue of a session; the colours
  read again with the theme; the worker that paints on an `OffscreenCanvas`; the `<turbo-stream action="celebrate">`
  that `celebrate()` of phil/ux-kit writes. The controller `celebration` and the modules beside it,
  `celebration_effects.js` and `celebration_worker.js`, are those of the PHIL UX kit.

### Changed

- The development requirements take `twig/twig` and `twig/html-extra` 3.31.1 or later, which fixes the advisories of its
  sandbox published on 2026-11-24.

## 0.2.0-alpha - 2026-11-24

### Added

- The recipes `ThemeSwitcher` and its `Script`, `StickyHeader`, `Timeline` and `FloatingLabel`, under the names,
  props, blocks and Stimulus controllers of the PHIL UX kit, the controllers `theme-switcher` and `sticky-header` the
  same files, written in Tailwind with the Shadcn kit's tokens and `data-slot` markers: the switcher's options styled
  as the kit's toggles, the header's scroll padding set on the root through `--phil-sticky-header-size`, its slide
  turned off under `motion-reduce`, the markers of the timeline in the colour of the text under forced colours.

### Changed

- `ComingSoon` keeps its illustration at least 40 % of the screen's height in `svh`, which stays put while the bars of
  a phone's browser come and go.
- The browser suite audits with axe-core 4.14.1.
- The development requirements ask for the latest minor of each dependency (`twig/twig` 3.31,
  `symfony/ux-twig-component` 3.8, `symfony/ux-toolkit` 3.8, `twig/extra-bundle` 3.31, …).

## 0.1.0-alpha - 2026-11-14

### Added

- `manifest.json` and `icon.svg`: the kit for the Symfony UX Toolkit, depending on the npm packages and importmap
  entries of the Shadcn kit, `shadcn` and `tw-animate-css`.
- The recipes `Logo`, `AnimatedHeadline` (with the Stimulus controller `animated-headline`, the same file as in the
  PHIL UX kit), `Illustration` and the block `ComingSoon`, under the names, props, blocks and controller of the PHIL UX
  kit, written in Tailwind with the Shadcn kit's tokens and `data-slot` markers; the headline's pause/play button
  styled as the kit's outline button.
- The recipe `AutosaveStatus`, under the props and Stimulus attributes of the PHIL UX kit: the live region and the
  notice of a form's draft for the autosave controller of `phil/forms-autosave`, its buttons styled as the kit's
  outline button.
- The recipes `ReactionButton` and `ReactionPicker`, under the props and Stimulus attributes of the PHIL UX kit, for
  `phil/reactions-bundle`, with a touch target of `min-h-11 min-w-11`; of the attributes of a button, only `name`,
  `value`, `title`, `data-*` and `aria-*`.
- The recipe `image`, `Image` and `Picture`, under the names, props and allowed attributes of the PHIL UX kit and the
  prop names of Symfony UX Image, without a service, JavaScript or inline style: the sources in their order, AVIF first,
  by density or by width, `sizes` after `auto,` while lazy, `priority`, `caption` in a `figure`, `layout`, `aspect`,
  `fit` and `position` as Tailwind utilities (`max-w-none`, `w-full`, `aspect-video`, `aspect-(--image-ratio)`,
  `object-cover`, `object-top`…), art direction with the sources of a query first, refused `javascript:`, `vbscript:`
  and non-image `data:` URLs; `block h-auto max-w-full` with `data-slot="image"`, `data-slot="picture"` on the picture,
  written with `html_attr()` of `twig/html-extra`. `Logo` renders through this `Picture`, with `avif` and `srcset`, its
  image sized by `[&_img]:h-12 [&_img]:w-auto` on its root.
- The recipe `QrCode`, around the component of `phil/qr-code-bundle`, under the props of the PHIL UX kit, laid out in
  Tailwind.
- `.gitattributes`: the archive `ux:install` downloads holds the recipes alone.
- The Tailwind versions of `ColorPicker`, `CopyButton`, `AnimatedButton`, `Stepper`, `ActivityCalendar`, `Tree`,
  `Rating`, `NumberInput`, `FileInput`, `Tag`, `RelativeTime`, `ProgressRing`, `Sparkline` and `BeforeAfter`, under the
  names, props, blocks and controllers of the PHIL UX kit; what the controllers create takes its classes from the
  template, through the `classes` of Stimulus. No `DatePicker`: the Shadcn kit's own holds the name.
- The Tailwind versions of the blocks `LoginForm` and `SignupForm` and of the charts `Chart` and `ChartJs`, under the
  names, props and blocks of the PHIL UX kit; the series of a chart in the `chart-1` to `chart-5` tokens of the Shadcn
  kit, each line from the second on with a dash of its own.
- The browser suite: the recipes a user acts on, in Chrome through `symfony/panther`, against a stylesheet compiled with
  Tailwind, in clear, in dark and right to left, on the test case and the page of recipes that the PHIL UX kit publishes
  in `src/Test/`, with its own pinned Stimulus.
- On `Image`, `Picture` and `Logo`, the props of the PHIL UX kit 0.3: `preload` through Symfony WebLink, `dark` and
  `still`, `credit`, `license` with `licenseHref`, `creation` and `creationLabel` in the new `Image:Figure`
  (`data-slot="image-credit"`, `image-license`, `image-creation`), the images of other hosts refused through
  `image_origin()` of `phil/ux-kit` unless listed; the image recipe requires `phil/ux-kit` and `symfony/web-link`, as
  in the PHIL UX kit.
- The recipes `Gallery`, a list in a grid on a container query (`@container`, `@3xl:gap-4`), and `Lightbox`, a native
  modal `dialog` opened by the invokers of HTML, with the Stimulus controller `lightbox`, the same file as in the PHIL
  UX kit.
- On `Image`, `Picture`, `Logo`, `Gallery` and `Lightbox`, the props of the PHIL UX kit 0.4: `deferred`
  (`fetchpriority="low"`), `placeholder` (`bg-muted`, `bg-primary/10`, `bg-blue-100 dark:bg-blue-950` and the other
  tones, `bg-(--image-placeholder)`), `preview` (`bg-cover bg-center bg-no-repeat`, painted by the Stimulus controller
  `image-preview`, the same file as in the PHIL UX kit, which the image recipe copies), `forced` (a source for
  `forced-colors: active`), `description` and `descriptionLabel` (`data-slot="image-description"`, a `details` linked
  by `aria-describedby`), and the warnings of `image_check()` in development; a deferred gallery skips its figures off
  screen with `[content-visibility:auto]`, each `li` at the ratio of its `data-width` and `data-height` under
  `supports-[…]`, its cell reaching into the gap by the ring of the focus; the lightbox's button keeps a system border
  where colours are forced; the image recipe's manifest is the PHIL UX kit's.
- On `Logo`, `priority`, `layout`, `aspect`, `fit` and `position`; on `ComingSoon`, `logoWidth`, `logoHeight`,
  `logoPriority`, `logoLayout`, `logoAspect`, `logoFit`, `logoPosition` and `illustrationLoading`, under the props of
  the PHIL UX kit 0.4; by default, the page is unchanged.
- On `Image` and `Picture`, `captionPosition` of the PHIL UX kit 0.5: `overlay` lays the caption over the foot of the
  image, the figure `grid w-fit max-w-full`, the image and the caption in one cell, the caption
  `self-end bg-black/70 text-white`; on `Gallery`, `layout="carousel"`, a row that scrolls and snaps
  (`grid-flow-col snap-x snap-mandatory overflow-x-auto`), its markers and buttons drawn by `::scroll-marker` and
  `::scroll-button()` through arbitrary variants, named by `data-marker`, `data-previous` and `data-next`, with
  `previousLabel`, `nextLabel` and `captionPosition`.
- The recipes `Hotspots`, `Hero`, `Video` and `Figure`, under the names, props and blocks of the PHIL UX kit 0.5: the
  points of an image placed by `start-[…]` and `top-[…]` to the nearest five per cent and exactly by a typed `attr()`
  under `supports-[…]`; a hero whose image covers its box, `priority` by default, its content on a veil; a video with
  `preload="none"`, its poster, sources, tracks and transcript; several images in a grid under one caption.
- The browser suite pins axe-core 4.14.0 and audits the images of 0.5 in each mode; its page plays the WebM clip and
  WebVTT captions of `tests/Browser/resources/media`.
- The recipe `Celebration`, under the name, props, block and Stimulus controller of the PHIL UX kit 0.5, the
  `celebration_controller.js` file the same: its message in a polite live region `m-0 text-lg font-semibold`, its root
  `flex flex-col items-start gap-2` with `data-slot="celebration"`, its canvas in a `template`,
  `pointer-events-none fixed inset-0 z-50 size-full`, hidden by `motion-reduce:hidden` and
  `in-data-[motion=reduce]:hidden`, drawn in the `--primary` and `--chart-1` to `--chart-5` tokens; the browser suite
  plays it by its button and by the DOM event `celebrate`, checks that nothing is drawn under reduced motion, and audits
  it with axe-core in each mode.
- On `Image` and `Picture`: `structured`, under the name of the PHIL UX kit 0.5, which describes the image as a
  schema.org `ImageObject` in JSON-LD through `image_json_ld()` of `phil/ux-kit` and `phil/structured-data`, in the
  graph of the page or in a script of its own before the image, without a nonce; the image README documents
  `image_filters()` on LiipImagine and `picture_props()` from a provider, which the props of the kit take as they are.
- On `Image` and `Picture`, under the names of the PHIL UX kit 0.5: `frame`, `rounded-lg`, `border border-border` and
  `shadow-md`, or `token`, `aspect-square object-cover rounded-full border-4 border-primary` in place of the ratio, the
  fit, the corners and the line; `pixelated`, `[image-rendering:pixelated]`; `transition`, `data-transition` read into
  `view-transition-name` by a typed `attr()` under `supports-[…]`, `--image-transition` elsewhere, never a `style`
  attribute. `Gallery`, `Lightbox` and `Hotspots` carry them as the PHIL UX kit does.
- The images on paper, through the `print:` variant: a figure, an item of a gallery, a lightbox, an image with points
  and a hero never cut, a caption kept with its image, an image within `max-h-[85vh]` and shown whole unless it covers
  its box, what lies over an image printed with its veil, a carousel printed as a grid without markers or buttons, a
  deferred gallery rendered whole, the close button and backdrop of a lightbox and the canvas of a celebration left
  out; right to left, the buttons of a carousel point the other way, by `rtl:`.
- The browser suite runs the tests of the images and of the celebration under the strict Content Security Policy of
  `RecipesPage` too, finds nothing refused on any strict page and sees a `style` attribute refused; it checks the frames,
  the token, the pixel art and a view transition, the images on paper and right to left, and audits the frames and a
  gallery of lightboxes with axe-core.
- The browser suite compares each layout of the images, an image `fixed`, `constrained`, `full-width` and cropped to a
  ratio, a gallery in a grid and a figure of three columns, with reference screenshots of its own, through
  `BrowserTestCase::assertLooksLike()` of the PHIL UX kit 0.5.
- The recipe `Carousel`, with `Carousel:Content`, `Carousel:Item`, `Carousel:Previous` and `Carousel:Next`, under the
  names, props and Stimulus controller of the PHIL UX kit 0.5, the `carousel_controller.js` file the same: slides on
  the native scroll snapping of the browser (`[scroll-snap-type:inline_mandatory]`, a slide
  `basis-[var(--carousel-item,100%)]`, a vertical carousel within `--carousel-size`), in a named region on a grid of
  three columns, its buttons drawn inline with the classes of each `variant` and `size` of the Shadcn kit's button,
  its pause button when it plays, printed one slide after the other without its buttons; no carousel library, and no
  recipe of the Shadcn kit named by its manifest. The browser suite scrolls it at its button and its arrow key, in
  clear, in dark and right to left.
- The recipes follow the classes of the Shadcn kit 3.7: `focus-visible:ring-3`, `rounded-lg` and the button heights
  of the Nova style, `ReactionPicker` on `rounded-lg ring-1 ring-foreground/10`, the fields of `LoginForm` and
  `SignupForm` as the kit's `Input` when invalid; logical classes, `[&_th]:text-start` in `Chart` and `ChartJs` and
  `slide-in-from-end-8` in `ComingSoon`, from tw-animate-css 1.3.4.
- A test installs recipes with `ux:install` from the archive of the kit into the subdirectory `ui`, and checks the
  identifiers of the kit's controllers prefixed and those of a bundle's controller left alone.
- `phil/ux-kit` required in development at `^0.5@dev`, `phil/structured-data` at `^0.1@dev`, the Symfony UX
  Toolkit at `^3.7.1`, and `ext-zip` for the archive of the kit the install test serves.
