<!-- phil-dev source-sha256=f6577f7d6f244914ebf7bb6c513497710cf770f810f8f80e1125da11f97b9659 -->
# Référence

## Fichiers

| Fichier | Ce qu'il contient |
|---|---|
| `css/phil-ux.css` | L'entrée : l'ordre des couches et un import par fichier |
| `css/tokens/palette.css` | La palette OKLCH, couche `phil.palette` |
| `css/tokens/colors.css` | Les rôles de couleur et `data-theme`, couche `phil.colors` |
| `css/tokens/dimensions.css` | Les échelles, les polices, le focus et le mouvement, couche `phil.dimensions` |
| `css/layers/base.css` | La base sans classe : typographie, formulaires, boutons, tableaux, dialogues, dévoilements, navigation, jauges, bulles ; couche `phil.base` |
| `css/layers/layout.css` | Les classes de mise en page, couche `phil.layout` |
| `css/layers/components.css` | Les composants, le code coloré et le repli d'impression d'UJO (Universal JSON Output), puis les tonalités et les tailles, couche `phil.components` |
| `css/layers/utilities.css` | Les utilitaires, couche `phil.utilities` |
| `dist/phil-ux.css` | Le tout en un seul fichier, écrit par `bin/build.php` |
| `dist/tokens.json` | Les jetons au format du Design Tokens Community Group, écrits par `bin/build.php` |
| `bin/build.php` | Écrit `dist/phil-ux.css` et `dist/tokens.json` à partir de `css/` |
| `package.json` | Le paquet npm `@phil/ux-css`, son style `dist/phil-ux.css`, et `tokens.json` |

## Classes

| Famille | Classes |
|---|---|
| Tonalités | `isPrimary` `isNeutral` `isDanger` `isSuccess` `isWarning` `isInfo` |
| Tailles | `isSmall` `isLarge` |
| Boutons | `isButton` `isSecondary` `isGhost` `isOutline` `isLoading` `hasIcon` `isIconOnly` `isButtonGroup` |
| État | `isBadge` `isAlert` `isLoud` `isSpinner` `isSkeleton` |
| Surfaces | `isCard` `isCardMedia` `isCardBody` `isCardTitle` `isCardLink` `isEmptyState` `isSheet` `isStart` `isTop` `isBottom` |
| Contrôles | `isSwitch` `isTabs` `isDropdown` `isDropdownMenu` `isTooltip` `hasTooltip` `isAccordion` |
| Parcours | `isBreadcrumb` `isPagination` `isStepper` `isDone` |
| Contenu | `isAvatar` `isSeparator` `isVertical` `isCarousel` `isActivityCalendar` `isStriped` `hasHover` `isScrollable` `isQrCode` `isQrCodeCaption` |
| Formulaires | `isField` `isHorizontal` `isResponsive` `isFieldSet` `isToggle` `isToggleInput` `isToggleGroup` `isRadioGroup` `isLoginForm` `isSignupForm` |
| Listes et boîtes | `isItem` `isItemGroup` `isScrollArea` `isAspectRatio` `isCollapsible` `isKbdGroup` |
| Graphiques | `isChart` `isChartImage` `isChartSeries` `isChartBar` `isChartLine` `isChartLegend` `isChartTable` `hasChartJs` `isChartCanvas` |
| Affichage | `isEyebrow` `isHeadline` `isReveal` `isRevealToggle` `isLogo` `isImage` `isProse` `isReaction` `isReactionPicker` `isReactionPickerPanel` |
| Mise en page | `isStack` `isCluster` `isGrid` `isCenter` `isNarrow` `isSplit` `isFullHeight` `isCover` `isCoverBody` `isBleed` `hasEntrance` |
| Utilitaires | `isVisuallyHidden` `isSkipLink` `isMuted` `isGrow` |

## Types

| Type | Nature | Rôle |
|---|---|---|
| `Phil\UxCss\Color\Oklch` | `final readonly class` | Une couleur OKLCH : lue, convertie, sa gamme, sa luminance, un rapport de contraste |
| `Phil\UxCss\Token\TokenSheet` | `final readonly class` | Les propriétés personnalisées de feuilles de style, résolues dans un schéma |
| `Phil\UxCss\Token\Tone` | `final readonly class` | Un ton de la palette : teinte, numéro, couleur |
| `Phil\UxCss\Token\Scheme` | enum | Clair ou sombre, le côté de `light-dark()` lu |
| `Phil\UxCss\Token\DesignTokens` | `final readonly class` | Les propriétés personnalisées d'une feuille au format du Design Tokens Community Group |
| `Phil\UxCss\Token\Calc` | `final class` | Une longueur, une durée ou un nombre, `calc()` et `var()` calculés dans des bornes |
| `Phil\UxCss\Token\Quantity` | `final readonly class` | Un nombre et son unité |
| `Phil\UxCss\Token\CssValue` | `final class`, interne | Les listes, familles de polices et courbes de Bézier cubiques que lit `DesignTokens` |
| `Phil\UxCss\Contrast\ContrastRule` | `final readonly class` | La règle de contraste, `TEXT` (4.5) et `NON_TEXT` (3.0) |
| `Phil\UxCss\Contrast\Pair` | `final readonly class` | Un premier plan, un arrière-plan, un minimum |
| `Phil\UxCss\Contrast\Violation` | `final readonly class` | Une paire sous son minimum, ou un jeton qui ne se résout pas |
| `Phil\UxCss\Stylesheet\StylesheetBundler` | `final readonly class` | Les imports en couche d'une entrée intégrés |
| `Phil\UxCss\Exception\UxCssException` | interface | Chaque exception de la librairie |
| `Phil\UxCss\Exception\InvalidColor` | `final class` | Une couleur qui n'est pas écrite en `oklch()` |
| `Phil\UxCss\Exception\UnreadableSheet` | `final class` | Une feuille de style illisible, ou un import hors du dossier de l'entrée |
