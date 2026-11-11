# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\MarkupBundle\PhilMarkupBundle` | `AbstractBundle` | The `phil_markup` configuration and the services |
| `…\Infrastructure\DependencyInjection\MarkupServices` | `final class` | Registers the services; `formats()`, `isEnabledDefault()` for the configuration |
| `…\Infrastructure\DependencyInjection\MediaHostsPass` | compiler pass | Gives `SanitizerPurifier` the `media_hosts`, whichever bundle registered it |
| `…\Infrastructure\DependencyInjection\SmartPunctuationPass` | compiler pass | Gives the smart punctuation the typographers of `phil/typography-bundle`, or its own, and its formats |
| `…\Infrastructure\Typography\SmartPunctuationCompiler` | `final readonly class` | A `LevelAwareCompiler` decorating `MarkupCompiler`: its HTML through a typographer |
| `…\Infrastructure\Format\MarkupFormats` | `final readonly class` | `enabled()`, `isEnabled()`, `format()`, `label()`, `level()`, `ceiling()`; formats by name |
| `…\Infrastructure\Format\UnavailableFormat` | exception | A format not enabled: see [Exceptions](exceptions.md) |
| `…\Infrastructure\Conversion\UjoConverter` | `final readonly class` | `reader()`, `read()`, `toJson()`: see [Conversion into UJO](conversion.md) |
| `…\Infrastructure\Form\MarkupFormatType` | form type | A choice of an enabled format: see [Forms](forms.md) |
| `…\Infrastructure\Twig\MarkupExtension` | Twig runtime | `markup_html`, `markup_purify` and `markup_ujo`: see [Twig](twig.md) |

## Services

| Id | |
|---|---|
| `Phil\Ujo\Html\HtmlRenderer` | built by `HtmlRenderer::default()` |
| `Phil\Markup\Adapter\DefaultCompiler`, alias `Phil\Markup\MarkupCompiler` | renders UJO with `HtmlRenderer` |
| `Phil\Markup\Adapter\SanitizerPurifier`, alias `Phil\Markup\HtmlPurifier` | built by `defaultPolicies(media_hosts)` |
| `phil_markup.smart_punctuation` | `SmartPunctuationCompiler`, decorating `Phil\Markup\MarkupCompiler`; with `phil/typography`, unless `smart_punctuation` is off |
| `phil_markup.typographers` | `Phil\Typography\Typographers` of the smart punctuation, when `phil/typography-bundle` registers none |
| `Phil\Markup\Markup` | public |
| `Phil\MarkupBundle\Infrastructure\Format\MarkupFormats` | public; `formats`, `default_format`, `default_level`, `levels`, the labels of `added_formats` |
| `Phil\MarkupBundle\Infrastructure\Conversion\UjoConverter` | public; reads with `DefaultCompiler` |
| `Phil\MarkupBundle\Infrastructure\Form\MarkupFormatType` | with Symfony Form, tagged `form.type` |
| `Phil\MarkupBundle\Infrastructure\Twig\MarkupExtension` | with Twig |

## Translations

`translations/phil_markup.en.php` and `translations/phil_markup.fr.php`: the labels of the formats.

The formats, levels, readers and adapters are `phil/markup`'s, the renderer `phil/ujo`'s: see their references.
