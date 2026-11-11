# phil/ux-css

**phil-ux, the CSS framework of PHIL: real, mostly classless CSS with `--phil-*` tokens, light and dark.**

phil-ux dresses the HTML elements without a class first — the page, its typography, forms as Symfony renders them,
buttons, tables, dialogs, disclosures, navigation, gauges and popovers — and adds classes that say what an element is
or has, in camelCase: `isSplit`, `isDanger`, `hasIcon`. It is plain CSS in cascade layers, with no build step and no
Tailwind: a site's own styles, written outside any layer, win over it without `!important`.

Every value is a token. The scales are named in t-shirt sizes and follow global multipliers. The colours come from an
OKLCH palette numbered by lightness, and components read colour roles — a fill, a border and the colour on that fill,
each quiet or loud —, never the palette. One theme holds light and dark in the same declarations; the page follows the
system unless `data-theme` chooses. The package also ships the PHP contrast rule the theme is tested against, which a
site runs on its own overrides.

The recipes of the PHIL UX kit for the Symfony UX Toolkit take their look from this stylesheet.

## Status

| Part | What it is | Status |
|---|---|---|
| Tokens: dimensions | Space, text, radius, shadow in t-shirt sizes, fonts, motion, global multipliers | Implemented and tested |
| Tokens: palette | Seven OKLCH hues numbered by lightness, in sRGB | Implemented and tested |
| Tokens: colour roles | Surface, text, link, focus; the fill/border/on × quiet/loud grid of six roles; light and dark | Implemented and tested |
| Classless base | Typography, page elements, focus ring, forms and Symfony's form markup, buttons, tables, dialogs, disclosures, navigation, gauges, popovers, reduced motion | Implemented and tested |
| Layout | `isStack`, `isCluster`, `isGrid`, `isCenter`, `isNarrow`, `isSplit`, `isFullHeight`, `isCover`, `isBleed`, `hasEntrance` | Implemented |
| Display components | `isEyebrow`, `isHeadline`, `isReveal` (the clip reveal), `isLogo`, `isProse`, `isReaction`, `isReactionPicker` | Implemented |
| Tones and sizes | `isPrimary`, `isNeutral`, `isDanger`, `isSuccess`, `isWarning`, `isInfo`, `isSmall`, `isLarge` | Implemented and tested |
| Button looks | `isButton`, `isSecondary`, `isGhost`, `isOutline`, `isLoading`, `hasIcon`, `isIconOnly`, `isButtonGroup` | Implemented and tested |
| Interface components | `isBadge`, `isAlert`, `isCard`, `isEmptyState`, `isTabs`, `isSheet`, `isSwitch`, `isTooltip`, `isDropdown`, `isAccordion`, `isBreadcrumb`, `isPagination`, `isStepper`, `isCarousel`, `isSpinner`, `isSkeleton`, `isAvatar`, `isSeparator`, `isActivityCalendar`, `isQrCode`, `isField`, `isItem`, `isToggle`, `isRadioGroup`, `isScrollArea`, `isAspectRatio`, `isCollapsible`, `isKbdGroup`, `isChart`, `isLoginForm`, `isSignupForm` | Implemented and tested |
| Highlighted code, print fallback | The `hl-*` classes in colour roles; `.ujo-print-fallback` shown only on paper | Implemented and tested |
| Symfony Flex recipe | In the recipes repository of PHIL: maps `dist/` in AssetMapper | Implemented |
| Utilities | `isVisuallyHidden`, `isSkipLink`, `isMuted`, `isGrow` | Implemented |
| `dist/phil-ux.css` | The single-file build, its freshness tested | Implemented and tested |
| `ContrastRule`, `TokenSheet`, `Oklch` | The contrast rule, checked on the package's tokens and on a site's overrides | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | AssetMapper, npm, a copied file; the cascade layers. |
| [Tokens](doc/tokens.md) | The scales, the palette, the colour roles, and the tokens as DTCG JSON for design tools. |
| [Themes](doc/themes.md) | Light and dark, `data-theme`, a site's overrides. |
| [Elements](doc/elements.md) | What the classless base dresses, forms and Symfony's markup included. |
| [Layout](doc/layout.md) | The layout classes. |
| [Components](doc/components.md) | Tones and sizes, buttons, every component, the utilities. |
| [The contrast rule](doc/contrast.md) | The rule, and how a site checks its overrides with it. |
| [Extension points](doc/extension-points.md) | What a site changes, and how. |
| [Testing](doc/testing.md) | How the package is tested. |
| [Reference](doc/reference.md) | Every file and type of the package. |

## Quick start

```html
<html lang="en" data-theme="dark">
<link rel="stylesheet" href="/styles/phil-ux.css">

<div class="isSplit isFullHeight">
    <div class="isCover">
        <header>…</header>
        <main class="isCoverBody">
            <hgroup>
                <p class="isEyebrow">Coming soon</p>
                <h1 class="isHeadline">Studio</h1>
            </hgroup>
        </main>
    </div>
    <figure class="isBleed hasEntrance"><img src="/images/main.png" alt=""></figure>
</div>
```

## Requirements

A browser of 2024 or later, for cascade layers, `light-dark()`, `oklch()` and `:user-invalid`; the animation of
`details` and of the top layer come with the browsers that have them, and the rest works without. PHP 8.5 or later for
the contrast rule, with no extension.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
