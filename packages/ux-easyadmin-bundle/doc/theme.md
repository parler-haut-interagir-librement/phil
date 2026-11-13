# The theme

`phil-ux-easyadmin/phil-ux-easyadmin.css` gives EasyAdmin's variables the values of the PHIL UX roles. It changes
values only: EasyAdmin keeps its markup, its components and its templates.

## The layers

EasyAdmin puts every style of the back-office in cascade layers, declared in this order:
`ea-overrides, ea-defaults, vendor, ea`, with `ea` divided into `ea.tokens`, `ea.base`, `ea.prose`,
`ea.components` and `ea.utilities`. A later layer wins; a style outside any layer wins over all of them.

| Part of the stylesheet | Layer | Effect |
|---|---|---|
| The palette, the colour roles and the dimensions of phil/ux-css | `vendor.phil.palette`, `vendor.phil.colors`, `vendor.phil.dimensions` | The `--phil-*` custom properties, which no style of EasyAdmin defines; everything else they set loses to EasyAdmin |
| The theme | `phil-easyadmin`, declared after `ea` | EasyAdmin's variables on the PHIL UX roles, winning over `ea.tokens` |
| The application's own styles | none | Win over the theme and over EasyAdmin |

The base, layout, component and utility layers of phil/ux-css are left out. Their rules on elements — `button`,
`input`, `code`, a `form div` — would restyle EasyAdmin's markup from any layer declared after `ea`, and their classes
are not in EasyAdmin's markup. The back-office's buttons, alerts and badges are EasyAdmin's, in PHIL UX colours.

The stylesheet is one file: `bin/build.php` writes it from `assets/src/phil-ux-easyadmin.css`, each import of a
token file replaced by that file in its layer, so the back-office loads one stylesheet and AssetMapper resolves no
import across two packages.

## The variables

| EasyAdmin variables | PHIL UX roles |
|---|---|
| `--ea-primary`, `--ea-primary-foreground` | `--phil-color-brand-fill-loud`, `--phil-color-brand-on-loud` |
| `--ea-primary-on-surface` | `--phil-color-link` |
| `--ea-primary-mix-base`, `--ea-primary-contrast-base`, from which EasyAdmin mixes its tints of the accent | `--phil-color-surface`, `--phil-color-text` |
| `--body-bg`, `--content-bg`, `--sidebar-bg`, `--secondary-bg` | `--phil-color-surface`, `--phil-color-surface-raised`, `--phil-color-neutral-fill-quiet` |
| `--sidebar-menu-color`, `--sidebar-menu-active-item-bg`, `--sidebar-menu-active-item-color` | `--phil-color-text`, `--phil-color-brand-fill-quiet`, `--phil-color-brand-on-quiet` |
| `--text-color`, `--text-muted`, `--link-color`, `--link-hover-color`, `--border-color` | `--phil-color-text`, `--phil-color-text-muted`, `--phil-color-link`, `--phil-color-border` |
| `--table-cell-border-color`, `--table-hover-cell-bg` | `--phil-color-border`, `--phil-color-neutral-fill-quiet` |
| `--form-input-border-color`, `--form-input-text-color` | `--phil-color-neutral-border-loud`, `--phil-color-text` |
| `--alert-<state>-bg`, `-color`, `-border-color` for primary, secondary, success, info, warning and danger | The quiet fill, the text on it and the quiet border of the brand, neutral, success, info, warning and danger roles |
| `--badge-<state>-bg`, `-color` for the same states | The quiet fill and the text on it of the same roles |
| `--ea-radius`, `--border-radius-sm`, `--border-radius`, `--border-radius-lg`, `--border-radius-xl` | `--phil-radius-md`, `--phil-radius-sm`, `--phil-radius-md`, `--phil-radius-lg`, `--phil-radius-xl` |
| `--ea-focus-ring-width` | `--phil-focus-width` |
| `--font-family-sans-serif`, `--font-family-monospace` | `--phil-font-sans`, `--phil-font-mono` |

The pairs the theme sets — a fill and the text on it — are the pairs whose contrast phil/ux-css checks: 4.5:1 for
text in both schemes. EasyAdmin's spacing, control heights and font sizes are kept: the back-office stays as dense as
EasyAdmin makes it.

## Dark mode

EasyAdmin chooses the scheme — light, dark, or the system's — and records it on the body: the class `ea-dark-scheme`
and the `color-scheme` property. The roles of phil/ux-css are `light-dark()` pairs, which take the tone of the
`color-scheme` of the element that uses them, so the theme follows the choice of the person using the back-office,
even against the system's, with no rule per scheme. The theme declares its variables on `:root, .ea-dark-scheme`, as
EasyAdmin declares its own, so they apply in both schemes. A dashboard that disables the dark mode gets the light
tones.

The icons of checked boxes and radio buttons are images whose colour EasyAdmin writes in the image: light on the
accent in the light scheme, dark on it in the dark scheme. The PHIL UX accent follows the same rule, so they keep
their contrast.

## Changing the theme

| To change | Write, outside any layer, in a stylesheet of the dashboard |
|---|---|
| A role, for the site and its back-office | `:root, .ea-dark-scheme { --phil-color-brand-fill-loud: light-dark(…, …); }` |
| One variable of EasyAdmin | `:root, .ea-dark-scheme { --sidebar-bg: …; }` |
| A rule of EasyAdmin | The selector, as EasyAdmin's documentation describes it |

An application whose styles are in a cascade layer of its own declares that layer after `phil-easyadmin`. EasyAdmin's
`Theme` options (`primaryColor()`, `grays()`, `radius()`, `font()`) are written outside any layer: when a dashboard
sets them, they win over the theme.
