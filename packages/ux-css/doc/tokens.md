# Tokens

Every token is a custom property of `:root` named `--phil-*`, in one of three files: `css/tokens/dimensions.css`,
`css/tokens/palette.css` and `css/tokens/colors.css`, each in its own layer. The CSS is the source; `dist/tokens.json`
gives the same tokens to design tools, in the format of the Design Tokens Community Group — see
[The tokens as JSON](#the-tokens-as-json).

## The scales

Each scale is computed from one unit and a global multiplier, so a site changes a whole scale at once.

| Multiplier | Default | Changes |
|---|---|---|
| `--phil-scale` | `1` | The spaces and the radii |
| `--phil-scale-space` | `var(--phil-scale)` | The spaces |
| `--phil-scale-text` | `1` | The text sizes |
| `--phil-scale-radius` | `var(--phil-scale)` | The radii |

| Scale | Sizes | Unit |
|---|---|---|
| `--phil-space-*` | `3xs` (⅛) `2xs` (¼) `xs` (½) `sm` (¾) `md` (1) `lg` (1½) `xl` (2) `2xl` (3) `3xl` (4) `4xl` (6) | `--phil-space-unit`, 1rem |
| `--phil-text-*` | `xs` `sm` `md` `lg` `xl` `2xl` `3xl` `4xl` | `--phil-text-md`, 1rem, on the ratio `--phil-text-ratio` (1.25) |
| `--phil-radius-*` | `none` `sm` (1) `md` (2) `lg` (4) `xl` (6) `full` | `--phil-radius-unit`, 0.25rem |
| `--phil-shadow-*` | `sm` `md` `lg` | Their colour is `--phil-color-shadow` |

The other dimensions: `--phil-leading-tight` and `--phil-leading-md`, `--phil-measure` (65ch) and
`--phil-measure-narrow` (36ch), the font stacks `--phil-font-sans`, `--phil-font-serif` and `--phil-font-mono` — the
system's own, nothing downloaded —, `--phil-focus-width` and `--phil-focus-offset`, the durations
`--phil-duration-sm`, `-md`, `-lg` and `-loop`, and the easings `--phil-ease-out` and `--phil-ease-in-out`.

| Token | Default | For |
|---|---|---|
| `--phil-weight-regular`, `-medium`, `-bold` | 400, 500, 700 | Font weights |
| `--phil-border-sm`, `-md`, `-lg` | 1px, 2px, 0.25rem | Lines and rules |
| `--phil-control-sm`, `-md`, `-lg` | 2rem, 2.75rem, 3.25rem | The height of a control; `md` is a touch target |
| `--phil-check-size` | 1.5rem | A check box, a radio, the height of a switch |
| `--phil-panel-sm`, `-md`, `-lg` | 20rem, 32rem, 48rem | The width of a menu, a dialog, a sheet |
| `--phil-z-raised`, `--phil-z-overlay` | 1, 10 | Stacking inside the page |
| `--phil-opacity-disabled` | 0.55 | What is disabled, a skeleton's pulse |
| `--phil-mix-hover`, `--phil-mix-active` | 12 %, 24 % | How far a fill moves towards the text colour |

## The palette

Seven hues — `neutral`, `brand`, `red`, `green`, `amber`, `blue`, `violet` — of eleven tones each, numbered by their
OKLCH lightness: `--phil-brand-40` is `oklch(0.40 …)`. The neutrals add `99`, the near-white of the page.

| Number | 05 | 10 | 20 | 30 | 40 | 50 | 60 | 70 | 80 | 90 | 95 |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Lightness | 0.05 | 0.10 | 0.20 | 0.30 | 0.40 | 0.50 | 0.60 | 0.70 | 0.80 | 0.90 | 0.95 |

Because the number is the lightness, the difference of two numbers gives the contrast, whatever the hues:

| Difference | Contrast reached | For |
|---|---|---|
| 50 or more | 4.5:1 | Text of any size |
| 40 or more | 3:1 | Large text, borders, icons, focus rings |

Each chroma is the hue's own, held within the sRGB gamut at every lightness, so a tone looks the same on every screen.

## The colour roles

Components read roles, never the palette. Each role is `light-dark(<light tone>, <dark tone>)`.

| Role | Light | Dark |
|---|---|---|
| `--phil-color-surface` | neutral 99 | neutral 10 |
| `--phil-color-surface-raised` | neutral 95 | neutral 20 |
| `--phil-color-text` | neutral 10 | neutral 95 |
| `--phil-color-text-muted` | neutral 40 | neutral 70 |
| `--phil-color-link` | brand 40 | brand 70 |
| `--phil-color-focus` | blue 50 | blue 70 |
| `--phil-color-border` | neutral 80 | neutral 30 |
| `--phil-color-shadow` | black at 12 % | black at 50 % |
| `--phil-color-backdrop` | black at 40 % | black at 60 % |
| `--phil-color-activity-0` | neutral 95 | neutral 20 |
| `--phil-color-activity-1` | brand 90 | brand 30 |
| `--phil-color-activity-2` | brand 70 | brand 50 |
| `--phil-color-activity-3` | brand 50 | brand 70 |
| `--phil-color-activity-4` | brand 30 | brand 90 |

### The grid

Six roles — `brand`, `neutral`, `danger` (red), `success` (green), `warning` (amber), `info` (blue) — each have six
colours: a fill, a border and the colour on that fill, each quiet (a tint) or loud (solid).

| Part | Light | Dark | Use |
|---|---|---|---|
| `fill-quiet` | 95 | 20 | The background of a tint: a notice, a selected row |
| `fill-loud` | 40 | 70 | The background of a solid: a primary button |
| `border-quiet` | 80 | 30 | A decorative border |
| `border-loud` | 50 | 60 | A border that must be seen, 3:1 on the surface |
| `on-quiet` | 30 | 90 | Text and icons on `fill-quiet` |
| `on-loud` | 95 | 10 | Text and icons on `fill-loud` |

```css
.isNotice {
    color: var(--phil-color-info-on-quiet);
    background: var(--phil-color-info-fill-quiet);
    border: 1px solid var(--phil-color-info-border-loud);
}
```

## The tokens as JSON

`dist/tokens.json` holds the tokens in the format of the W3C Design Tokens Community Group (2025.10), which design
tools read and write. It is generated from the CSS, which stays the source, by the same command as the single file:

```sh
php bin/build.php    # from the package, after a change under css/
```

The tests compare it with an export of the sources and fail while it is stale, so the continuous integration catches
a CSS change without its JSON. The npm package exports it as `@phil/ux-css/tokens.json`.

| CSS | JSON |
|---|---|
| `--phil-space-sm` | The token `sm` of the group `space`: the first word is the group, the rest the name |
| `--phil-scale` | The token `$root` of the group `scale`: a name of one word |
| `var(--phil-brand-40)` | The alias `{brand.40}`, of the type of what it names |
| `oklch(0.40 0.058 195 / 0.5)` | A `color`: `{"colorSpace": "oklch", "components": [0.4, 0.058, 195], "alpha": 0.5}` |
| `light-dark(A, B)` | The value of `A`; the value of `B` under `dark` in the token's extension |
| `1rem`, `2px` and `calc()` of them | A `dimension`, `calc()` computed at the default multipliers: `{"value": 0.75, "unit": "rem"}` |
| `150ms`, `1s` | A `duration` |
| `1.25`, `10` | A `number`; a `fontWeight` in the group `weight` |
| A list of fonts | A `fontFamily`, each family unquoted |
| `cubic-bezier(…)` | A `cubicBezier` |
| `0 1px 2px var(--phil-color-shadow)`, and lists of them | A `shadow`, or a list of shadows, each colour an alias or a value |
| `0` among the lengths of a group | A `dimension` of `0px` |

Each token carries, under the extension `ca.ph-il.phil-ux`, its custom property and its CSS as written, so that a tool
writing CSS back keeps the formulas:

```json
"sm": {
    "$type": "dimension",
    "$value": {"value": 0.75, "unit": "rem"},
    "$extensions": {
        "ca.ph-il.phil-ux": {"property": "--phil-space-sm", "css": "calc(var(--phil-space-unit) * 0.75)"}
    }
}
```

A value the format has no type for is left out, and named in the extension of the file: `--phil-measure` and
`--phil-measure-narrow`, in `ch`, and `--phil-mix-hover` and `--phil-mix-active`, in percent. `calc()` is computed
only where it is plain arithmetic of one unit — `+`, `-`, `*`, `/`, parentheses and `var()` —, within bounds on its
length, its pieces and the depth of `var()`; `Token\DesignTokens` does the export and `Token\Calc` the arithmetic, for
a site that exports its own overrides:

```php
use Phil\UxCss\Token\DesignTokens;
use Phil\UxCss\Token\TokenSheet;

$sources = [...DesignTokens::sources('vendor/phil/ux-css/css'), 'assets/theme.css'];
$json = DesignTokens::fromSheet(TokenSheet::fromFiles(...$sources))->toJson();
```
