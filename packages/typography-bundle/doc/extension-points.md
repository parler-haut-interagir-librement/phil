# Extension points

What an application configures, decorates or relies on. The typographers and fixers are `phil/typography`'s.

## Configuration

| Option | What it extends |
|---|---|
| `locales` | A typographer per locale, its preset or a list of fixer names in the order they run |
| `default_locale` | The locale of `Phil\Typography\Typographer`, and of the filters without a locale switcher |
| `hyphenation`, `experimental_hyphenation` | Whether presets hyphenate, and the experimental patterns served |
| `protected_tags` | The elements whose text is never changed |
| `ujo` | Whether, and in which locale, the HTML of UJO documents is fixed |

See [Configuration](configuration.md).

## Services

| Service | How an application relies on it |
|---|---|
| `Phil\Typography\Typographers` | Injected to fix text in any locale; public |
| `Phil\Typography\Typographer` | The default locale's; public |
| `phil_typography.typographer.<locale>` | One per key of `locales`; public |
| `Phil\Typography\Hyphenation\Dictionary` | Redefined by the application to read patterns it ships itself, from a directory of its own |
| `phil_typography.ujo_renderer` | Decorates `Phil\Ujo\Html\HtmlRenderer` with a `TypographyHtmlFilter`; other decorations of the renderer compose with it |

## Fixers of the application's own

A fixer of the application's — a class implementing `Phil\Typography\Fixer\Fixer` — runs in a typographer the
application builds with `new Typographer([...])`, the preset's fixers from `Preset::fixers()` among its own, and
registers under a service id of its choosing; the configured `locales` name the shipped fixers only.
