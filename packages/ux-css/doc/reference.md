# Reference

## Files

| File | What it holds |
|---|---|
| `css/phil-ux.css` | The entry: the layer order and an import per file |
| `css/tokens/palette.css` | The OKLCH palette, layer `phil.palette` |
| `css/tokens/colors.css` | The colour roles and `data-theme`, layer `phil.colors` |
| `css/tokens/dimensions.css` | The scales, fonts, focus and motion, layer `phil.dimensions` |
| `css/layers/base.css` | The classless base: typography, forms, buttons, tables, dialogs, disclosures, navigation, gauges, popovers; layer `phil.base` |
| `css/layers/layout.css` | The layout classes, layer `phil.layout` |
| `css/layers/components.css` | The components, the highlighted code and the print fallback of UJO (Universal JSON Output), then the tones and the sizes, layer `phil.components` |
| `css/layers/utilities.css` | The utilities, layer `phil.utilities` |
| `dist/phil-ux.css` | Everything as one file, written by `bin/build.php` |
| `dist/tokens.json` | The tokens in the format of the Design Tokens Community Group, written by `bin/build.php` |
| `bin/build.php` | Writes `dist/phil-ux.css` and `dist/tokens.json` from `css/` |
| `package.json` | The npm package `@phil/ux-css`, its style `dist/phil-ux.css`, and `tokens.json` |

## Classes

| Family | Classes |
|---|---|
| Tones | `isPrimary` `isNeutral` `isDanger` `isSuccess` `isWarning` `isInfo` |
| Sizes | `isSmall` `isLarge` |
| Buttons | `isButton` `isSecondary` `isGhost` `isOutline` `isLoading` `hasIcon` `isIconOnly` `isButtonGroup` |
| Status | `isBadge` `isAlert` `isLoud` `isSpinner` `isSkeleton` |
| Surfaces | `isCard` `isCardMedia` `isCardBody` `isCardTitle` `isCardLink` `isEmptyState` `isSheet` `isStart` `isTop` `isBottom` |
| Controls | `isSwitch` `isTabs` `isDropdown` `isDropdownMenu` `isTooltip` `hasTooltip` `isAccordion` |
| Paths | `isBreadcrumb` `isPagination` `isStepper` `isDone` |
| Content | `isAvatar` `isSeparator` `isVertical` `isCarousel` `isActivityCalendar` `isStriped` `hasHover` `isScrollable` `isQrCode` `isQrCodeCaption` |
| Forms | `isField` `isHorizontal` `isResponsive` `isFieldSet` `isToggle` `isToggleInput` `isToggleGroup` `isRadioGroup` `isLoginForm` `isSignupForm` |
| Lists and boxes | `isItem` `isItemGroup` `isScrollArea` `isAspectRatio` `isCollapsible` `isKbdGroup` |
| Charts | `isChart` `isChartImage` `isChartSeries` `isChartBar` `isChartLine` `isChartLegend` `isChartTable` `hasChartJs` `isChartCanvas` |
| Display | `isEyebrow` `isHeadline` `isReveal` `isRevealToggle` `isLogo` `isImage` `isProse` `isReaction` `isReactionPicker` `isReactionPickerPanel` |
| Layout | `isStack` `isCluster` `isGrid` `isCenter` `isNarrow` `isSplit` `isFullHeight` `isCover` `isCoverBody` `isBleed` `hasEntrance` |
| Utilities | `isVisuallyHidden` `isSkipLink` `isMuted` `isGrow` |

## Types

| Type | Kind | Role |
|---|---|---|
| `Phil\UxCss\Color\Oklch` | `final readonly class` | An OKLCH colour: read, converted, its gamut, its luminance, a contrast ratio |
| `Phil\UxCss\Token\TokenSheet` | `final readonly class` | The custom properties of stylesheets, resolved in a scheme |
| `Phil\UxCss\Token\Tone` | `final readonly class` | A tone of the palette: hue, number, colour |
| `Phil\UxCss\Token\Scheme` | enum | Light or dark, the side of `light-dark()` read |
| `Phil\UxCss\Token\DesignTokens` | `final readonly class` | The custom properties of a sheet in the format of the Design Tokens Community Group |
| `Phil\UxCss\Token\Calc` | `final class` | A length, a duration or a number, `calc()` and `var()` computed within bounds |
| `Phil\UxCss\Token\Quantity` | `final readonly class` | A number and its unit |
| `Phil\UxCss\Token\CssValue` | `final class`, internal | The lists, font families and cubic Béziers `DesignTokens` reads |
| `Phil\UxCss\Contrast\ContrastRule` | `final readonly class` | The contrast rule, `TEXT` (4.5) and `NON_TEXT` (3.0) |
| `Phil\UxCss\Contrast\Pair` | `final readonly class` | A foreground, a background, a minimum |
| `Phil\UxCss\Contrast\Violation` | `final readonly class` | A pair below its minimum, or a token that does not resolve |
| `Phil\UxCss\Stylesheet\StylesheetBundler` | `final readonly class` | The layered imports of an entry inlined |
| `Phil\UxCss\Exception\UxCssException` | interface | Every exception of the library |
| `Phil\UxCss\Exception\InvalidColor` | `final class` | A colour not written as `oklch()` |
| `Phil\UxCss\Exception\UnreadableSheet` | `final class` | A stylesheet that cannot be read, or an import outside the directory of the entry |
