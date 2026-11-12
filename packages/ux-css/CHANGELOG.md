# Changelog

All notable changes to `phil/ux-css` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.5.0-alpha - 2026-11-12

### Added

- `css/tokens/colors.css`: `--phil-color-veil`, black at 72 % in both schemes, and `--phil-color-on-veil`, the light
  text on it, which reaches 9:1 over a white picture.
- `css/layers/components.css`: `hasOverlayCaption`, a figure whose caption lies over the foot of its image on the veil;
  `isGallery.hasLayoutCarousel`, its figures in one row that scrolls and snaps, a marker per figure by `::scroll-marker`
  named by `attr(data-marker)`, and a button on each side by `::scroll-button()`, named by `attr(data-previous)` and
  `attr(data-next)`, where the browser draws them, right to left the button at the start standing on the right and
  pointing to it, that at its end to the left; `isHotspots`, `isHotspotsList` and `isHotspot`, the points over an
  image, placed by `hasHotspotX0` to `hasHotspotX100` and `hasHotspotY0` to `hasHotspotY100` in steps of five, or by a
  typed `attr()` of their `data-x` and `data-y`, a list under the image below 36rem; `isHero`, `hasHeroScreen`,
  `hasHeroLarge`, `hasHeroStart`, `hasHeroCenter`, `isHeroMedia` and `isHeroContent`; `isVideo`, kept at the ratio of its
  `width` and `height`; `isFigureGroup` with `hasTwoColumns`, `hasThreeColumns` and `hasFourColumns`.
- `css/layers/components.css`: the frames of an image drawn from the tokens, `hasRounded` (`--phil-image-radius`),
  `hasFramed` (`--phil-image-frame-width`, `--phil-image-frame-color`) and `hasRaised` (`--phil-image-shadow`); `hasToken`,
  a square box the picture covers, cropped to a disc inside a ring (`--phil-image-token-width`,
  `--phil-image-token-color`); `hasPixelated`, `image-rendering: pixelated`.
- `hasTransition`, the name of an image in a view transition: `--phil-image-transition`, set by a class of the site, or
  the `data-transition` of the image through a typed `attr()` where the browser reads one, Chromium 133 and later.
- The images on paper: a figure, an item of a gallery, a lightbox, an image with points and a hero never cut between two
  pages, a caption kept with its image, an image never taller than `--phil-image-print-height` (85% of the page) and
  shown whole unless it covers its box, a caption, the content of a hero or a point laid over an image printed with its
  veil, a carousel printed as the grid of a gallery without markers or buttons, a deferred gallery rendered whole, the
  close button and backdrop of a lightbox and the canvas of a celebration left out.
- `css/layers/components.css`: `isCelebration`, `isCelebrationMessage` and `isCelebrationCanvas`, the canvas of a
  celebration fixed over the whole page above its content, letting every click through, and never shown under reduced
  motion, whether the system asks for it or an ancestor carries `data-motion="reduce"`.
- `isAspectRatio` reads the ratio of its class, `hasRatio16x9`, `hasRatio4x3`, `hasRatio3x2`, `hasRatio1x1`,
  `hasRatio21x9`, `hasRatio3x4`, `hasRatio2x3` and `hasRatio9x16`, as an image does, or of its `data-ratio-width` and
  `data-ratio-height` through a typed `attr()` where the browser reads one, Chromium 133 and later; else
  `--phil-aspect-ratio`, else a square.
- `isBadge` reads the colour of its `data-color` through a typed `attr()` where the browser reads one, into
  `--phil-badge-color`.

### Changed

- An aspect ratio and the colour of a badge no longer need a `style` attribute, and hold under a strict Content
  Security Policy. `--phil-aspect-ratio` and `--phil-badge-color`, set by a class, still work everywhere; a ratio or a
  colour given only in a data attribute applies where the browser types `attr()`.
- The branch alias of `dev-main` is `0.5.x-dev`; `package.json` is at `0.5.0`.

### Fixed

- The dates of 0.3.0-alpha and 0.4.0-alpha, both released on 2026-11-11.

## 0.4.0-alpha - 2026-11-11

### Added

- `css/tokens/colors.css`: `--phil-color-placeholder-brand`, `-red`, `-green`, `-amber`, `-blue` and `-violet`, quiet
  tones in clear and in dark for the box of an image while it loads.
- `css/layers/components.css`: with `isImage`, `hasPlaceholderNeutral`, `hasPlaceholderBrand`, `hasPlaceholderRed`,
  `hasPlaceholderGreen`, `hasPlaceholderAmber`, `hasPlaceholderBlue`, `hasPlaceholderViolet`, and `hasPlaceholder`,
  which reads `--phil-image-placeholder` set by a class of the site; `hasPreview`, the blurred preview the kit paints
  laid out as the picture, covering or contained; `isDescription`, the disclosure of a long description under an
  image; `isGallery.isDeferred`, its cells rendered with `content-visibility: auto`, kept off screen at the ratio of
  their `data-width` and `data-height` read by a typed `attr()`, or in a square cell `--phil-gallery-column` high where
  the browser does not type it, their size remembered once rendered, and reaching into the gap by the width of the
  focus ring, which their paint containment would clip; where colours are forced, `isLightboxTrigger` keeps the frame
  of a system button.

### Changed

- The branch alias of `dev-main` is `0.4.x-dev`; `package.json` is at `0.4.0`.

## 0.3.0-alpha - 2026-11-11

### Added

- `css/layers/components.css`: `isCredit`, `isLicense` and `isCreation`, a line each under the caption of an image;
  `isGallery`, a container whose list lays its figures in a grid of columns `--phil-gallery-column` wide at least,
  read by a container query, its gap `--phil-gallery-gap` growing from 48rem; `isLightbox`, `isLightboxTrigger`, a
  button without a fill that keeps the focus ring, `isLightboxDialog`, a dialog as wide as the screen whose image is
  shown whole, and `isLightboxClose`, in its end corner.

### Changed

- The branch alias of `dev-main` is `0.3.x-dev`; `package.json` is at `0.3.0`.

## 0.2.0-alpha - 2026-11-11

### Added

- `css/layers/components.css`: `isImage`, an image of the content as a block, never wider than its container, its
  height following its width, for the `Image` and `Picture` components of the PHIL UX kit.
- With `isImage`: the layouts `hasLayoutFixed` and `hasLayoutFullWidth`; the ratios `hasRatio16x9`, `hasRatio4x3`,
  `hasRatio3x2`, `hasRatio1x1`, `hasRatio21x9`, `hasRatio3x4`, `hasRatio2x3`, `hasRatio9x16`, and `hasRatio`, which
  reads `--phil-image-ratio` set by a class of the site, never by an inline style; the fits `hasFitCover` and
  `hasFitContain`; the crops `hasPositionCenter`, `hasPositionTop`, `hasPositionBottom`, `hasPositionLeft` and
  `hasPositionRight`.

### Changed

- The branch alias of `dev-main` is `0.2.x-dev`; `package.json` is at `0.2.0`.

## 0.1.1-alpha - 2026-11-11

Published by mistake with the content of 0.1.0-alpha; use 0.2.0-alpha.

## 0.1.0-alpha - 2026-11-11

### Added

- `css/tokens/dimensions.css`: space, text, radius and shadow scales in t-shirt sizes (`--phil-space-3xs` to
  `--phil-space-4xl`, `--phil-text-xs` to `--phil-text-4xl` on a modular ratio), the global multipliers
  `--phil-scale`, `--phil-scale-space`, `--phil-scale-text` and `--phil-scale-radius`, system font stacks, line
  heights and measures, font weights, line widths, the heights of controls with a touch target of 2.75rem, the size of
  a check box, panel widths, stacking, the disabled opacity and the mixes of a hover and a press, the focus ring,
  durations and easings.
- `css/tokens/palette.css`: seven OKLCH hues — neutral, brand, red, green, amber, blue, violet — of eleven tones
  numbered by their lightness, 05 to 95, and the neutral 99; every tone in sRGB.
- `css/tokens/colors.css`: the colour roles in one theme, each `light-dark()` of two tones: the surface, the raised
  surface, the text, the muted text, the link, the focus ring, the border, the shadow, the backdrop and the five levels
  of an activity calendar; the fill/border/on ×
  quiet/loud grid of the roles brand, neutral, danger, success, warning and info. The page follows the system;
  `data-theme="light"`, `"default"` or `"dark"` chooses, on the root or on any element.
- `css/layers/base.css`: the classless base — box sizing, the page, headings, paragraphs, lists, `hgroup`, links,
  the focus ring, media, figures, `small`, `hr`, code, quotes, `mark`, `kbd`, `abbr`, `dt`, `dd`, `hidden`; forms —
  `fieldset`, `legend`, `label`, every type of `input`, `select`, `textarea`, `:disabled`, `:user-valid`,
  `:user-invalid`, `aria-invalid` — with the markup of Symfony's `form_row` dressed as it comes; `button` and its
  equivalents, reading six `--phil-tone-*` colours; tables with their overflow in a `figure`; `dialog`, modal or not,
  with its `::backdrop`, a popover having none, and fading in the top layer; `details` animated up to `height: auto`
  where the browser allows it;
  `nav` and `aria-current`; `progress`, `meter`; `[popover]` — and the reader's display preferences: the end
  of every animation under `prefers-reduced-motion` or `data-motion="reduce"`, the muted text and the borders at
  full contrast under `prefers-contrast: more` or `data-contrast="more"`, and the root at 112.5 % or 125 % under
  `data-text-size="large"` or `"larger"`.
- `css/layers/layout.css`: `isStack`, `isCluster`, `isCenter`, `isNarrow`, `isSplit` (two columns from 48rem),
  `isGrid`, `isFullHeight`, `isCover` with `isCoverBody`, `isBleed` and its slanted `hasEntrance`, each gap a custom
  property
  of its own.
- `css/layers/components.css`: `isEyebrow`, `isHeadline`, the clip reveal `isReveal` with its states `isRevealing`,
  `isPulsing` and `isCurrent` and the custom properties `--phil-reveal-size`, `--phil-reveal-duration` and
  `--phil-reveal-caret`, the pause/play button `isRevealToggle`, `isLogo`, `isProse`, the reaction `isReaction` with a
  touch target of 2.75rem and the picker `isReactionPicker` and `isReactionPickerPanel`; the tones `isPrimary`,
  `isNeutral`, `isDanger`, `isSuccess`, `isWarning` and `isInfo` on any component; the sizes `isSmall` and `isLarge`,
  inherited; the button looks `isButton`, `isSecondary`, `isGhost`, `isLink`, `isOutline`, `isLoading`, `hasIcon`,
  `isIconOnly` and
  `isButtonGroup`; `isBadge` and `isAlert` with `isLoud`; `isCard` with `isCardMedia`, `isCardBody`, `isCardTitle` and
  `isCardLink`; `isEmptyState`; `isTabs`; `isSheet` with `isStart`, `isTop` and `isBottom`; `isSwitch`; `isTooltip` with
  `hasTooltip`; `isDropdown` with `isDropdownMenu`; `isAccordion`; `isBreadcrumb`; `isPagination`; `isStepper` with
  `isDone`; `isCarousel`; `isSpinner`; `isSkeleton`; `isAvatar`; `isSeparator` with `isVertical`; `isActivityCalendar`;
  a class of its own for each part of the recipes of the kit, from `isCardHeader`, `isDialogClose`, `isSelectItem` or
  `isTabPanel` to `isTableRow`, listed in `doc/components.md`, the rules reading none of the recipes' data attributes;
  `isStriped`, `hasHover` and `isScrollable` for tables; `isQrCode` with `isQrCodeCaption`; the `hl-*` classes of
  highlighted code in colour roles; `.ujo-print-fallback`, the fallback of UJO (Universal JSON Output) media shown only
  on paper.
- `css/layers/utilities.css`: `isVisuallyHidden`, `isSkipLink`, `isMuted`, `isGrow`.
- `css/phil-ux.css`, the entry that orders the seven layers and imports each file into its own; `dist/phil-ux.css`,
  the same as one file; `dist/tokens.json`, the tokens in the format of the Design Tokens Community Group (2025.10),
  generated from the CSS, which stays the source; `bin/build.php`, which writes both, the tests failing while either
  is stale; `package.json` for npm, exporting `tokens.json`.
- `Color\Oklch`: an OKLCH colour read from CSS, converted to linear sRGB, its gamut, its WCAG relative luminance and
  the contrast ratio between two colours.
- `Token\TokenSheet`, `Token\Tone` and `Token\Scheme`: the custom properties of one or more stylesheets, the later
  winning; the tones of the palette, the roles of the grid, and a property resolved in a scheme through `var()`,
  their fallbacks and `light-dark()`.
- `Contrast\ContrastRule`, `Contrast\Pair` and `Contrast\Violation`: the contrast rule on a token sheet — tones 50
  apart at 4.5:1 and 40 apart at 3:1, every tone in sRGB, the pairs of the page, of the activity calendar and of the
  grid on both surfaces and in both schemes, and the pairs a site adds.
- `Stylesheet\StylesheetBundler`: the layered imports of an entry inlined as `@layer` blocks; an import outside the
  directory of the entry, absolute or climbing above it, refused.
- `Token\DesignTokens`, `Token\Calc` and `Token\Quantity`: the custom properties of a sheet as DTCG tokens — groups
  from the names, aliases from `var()`, colours, dimensions, durations, numbers, font weights and families, cubic
  Béziers and shadows, `calc()` computed within bounds, the dark value of `light-dark()` and the CSS as written in an
  extension, what has no type left out and named; the fuzz targets `calc` and `design-tokens`.
- `Exception\UxCssException`, `Exception\InvalidColor`, `Exception\UnreadableSheet`.
- `css/layers/components.css`: the classes of the recipes `ColorPicker`, `DatePicker`, `CopyButton`, `AnimatedButton`, `Tree`,
  `Rating`, `NumberInput`, `FileInput`, `Tag`, `RelativeTime`, `ProgressRing`, `Sparkline` and `BeforeAfter`; `isStepperItem`,
  `isStepperLabel`, `hasActivityCalendar`, `isActivityCalendarDay`, `isActivityCalendarBlank`, `isActivityLegend`; the
  arrow of
  `isTooltip`, on the edge named by `isTop`, `isBottom`, `isStart` or `isEnd`, in logical properties.
- `css/layers/components.css`: the classes of the recipes `AspectRatio`, `Collapsible`, `Kbd`, `Field`, `Item`,
  `ScrollArea`, `Toggle`, `ToggleGroup` and `RadioGroup` of the PHIL UX kit, of the blocks `LoginForm` and
  `SignupForm`, and of the charts: `isChart` and its parts, each series in a tone, the line of each tone but
  `isPrimary` in a dash of its own; `isHorizontal` and `isResponsive` on a field, `isHorizontal` and `isVertical` on a
  scroll area and a radio group.
