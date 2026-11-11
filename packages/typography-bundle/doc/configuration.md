# Configuration

```yaml
# config/packages/phil_typography.yaml
phil_typography:
    default_locale: ~            # the kernel's default locale when null
    hyphenation: true            # whether presets insert soft hyphens
    experimental_hyphenation: [] # hy, mk: experimental patterns, off by default
    protected_tags: [pre, code, kbd, script, style, textarea]
    locales:
        fr_CA: []                # a service with the preset of fr_CA
        en: [ellipsis, smart_quotes, curly_quote, dash]
    ujo:
        enabled: true            # fix the HTML of UJO documents, when phil/ujo-bundle is installed
        locale: ~                # the locale of the request when null
```

| Option | Default | Effect |
|---|---|---|
| `default_locale` | `null` | The locale of the `Typographer` service, and of the filters and the UJO pass when Symfony's `LocaleSwitcher` is not registered; `%kernel.default_locale%` when null |
| `hyphenation` | `true` | `false` leaves `hyphen`, and `word_break` for Thai, out of every preset; a locale configured with them keeps them |
| `experimental_hyphenation` | `[]` | The experimental hyphenation patterns served, among `hy` (Armenian) and `mk` (Macedonian); any other value is refused when the container is built. Without them, those languages have no hyphenation |
| `protected_tags` | `pre`, `code`, `kbd`, `script`, `style`, `textarea` | The elements whose text is never changed |
| `locales` | `{}` | A typographer service for each locale; a list of fixer names, in the order they run, replaces its preset — and serves the more precise locales of the same language —, an empty list keeps the preset. An unknown name is refused when the container is built |
| `ujo.enabled` | `true` | `false` leaves the HTML of UJO documents as `phil/ujo` renders it |
| `ujo.locale` | `null` | The locale of that pass; the current locale of the request when null, as the filters |

The fixer names are those of `Preset::FIXERS`: `unicode_normalization`, `ellipsis`, `dimension`, `dash`,
`smart_quotes`, `curly_quote`, `no_space_before_comma`, `space_before_punctuation`, `trademark`, `unit`, `hyphen`,
`word_break`.

## Services

| Service | Built as |
|---|---|
| `Phil\Typography\Hyphenation\Dictionary` | The shipped patterns, read once and shared, with the experimental ones of `experimental_hyphenation` |
| `Phil\Typography\Typographers` | On the options above; public |
| `Phil\Typography\Typographer` | `Typographers::for()`, the default locale; public |
| `phil_typography.typographer.<locale>` | `Typographers::for('<locale>')`, for each key of `locales`; public |
| `phil_typography.ujo_filter` | `TypographyHtmlFilter`, when `ujo.enabled` and `phil/ujo` is installed; with `translation.locale_switcher` when it exists |
| `phil_typography.ujo_renderer` | Decorates `Phil\Ujo\Html\HtmlRenderer` with that filter |

Each configured locale is also injected into an argument named after it, the locale camel-cased:

```php
public function __construct(
    private Typographer $typographer,          // the default locale
    private Typographer $enGBTypographer,      // phil_typography.typographer.en_GB
) {
}
```

`Typographers::for()` accepts any locale, configured or not: a locale without configuration gets its preset.
