# Changelog

All notable changes to `phil/ux-css` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-11

### Added

- `css/layers/components.css`: `isImage`, an image of the content as a block, never wider than its container, its
  height following its width, for the `Image` and `Picture` components of the PHIL UX kit.

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
