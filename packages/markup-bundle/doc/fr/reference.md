<!-- phil-dev source-sha256=9d9d01dfdb8a98c23d40c4bc2ebd7b2ea88db9119abf9f31ef9b720804f63817 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `Phil\MarkupBundle\PhilMarkupBundle` | `AbstractBundle` | La configuration `phil_markup` et les services |
| `…\Infrastructure\DependencyInjection\MarkupServices` | `final class` | Enregistre les services ; `formats()`, `isEnabledDefault()` pour la configuration |
| `…\Infrastructure\DependencyInjection\MediaHostsPass` | passe de compilation | Donne les `media_hosts` et les `purifier_max_bytes` à `SanitizerPurifier`, `AmmoniaPurifier` et `Purifiers`, quel que soit le bundle qui les a enregistrés ; garde `HtmlPurifier` sur le purificateur choisi |
| `…\Infrastructure\DependencyInjection\MaxBytesPass` | passe de compilation | Donne à chaque `DefaultCompiler` les `max_bytes` et `ujo_max_bytes`, quel que soit le bundle qui l'a enregistré ; un compilateur à qui l'on donne ses plafonds par position, ou construit par une fabrique, est laissé intact |
| `…\Infrastructure\DependencyInjection\SmartPunctuationPass` | passe de compilation | Donne à la ponctuation soignée les typographes de `phil/typography-bundle`, ou les siens, et ses formats |
| `…\Infrastructure\Typography\SmartPunctuationCompiler` | `final readonly class` | Un `LevelAwareCompiler` qui décore `MarkupCompiler` : son HTML passe par un typographe |
| `…\Infrastructure\Format\MarkupFormats` | `final readonly class` | `enabled()`, `isEnabled()`, `format()`, `label()`, `level()`, `ceiling()` ; les formats par nom |
| `…\Infrastructure\Format\UnavailableFormat` | exception | Un format non activé : voir [Exceptions](exceptions.md) |
| `…\Infrastructure\Conversion\UjoConverter` | `final readonly class` | `reader()`, `read()`, `toJson()` : voir [Conversion en UJO](conversion.md) |
| `…\Infrastructure\Form\MarkupFormatType` | type de formulaire | Un choix parmi les formats activés : voir [Formulaires](forms.md) |
| `…\Infrastructure\Twig\MarkupExtension` | environnement d'exécution Twig | `markup_html`, `markup_purify` et `markup_ujo` : voir [Twig](twig.md) |

## Services

| Identifiant | |
|---|---|
| `Phil\Ujo\Html\HtmlRenderer` | construit par `HtmlRenderer::default()` |
| `Phil\Markup\Adapter\DefaultCompiler`, alias `Phil\Markup\MarkupCompiler` | produit le rendu UJO avec `HtmlRenderer` |
| `Phil\Markup\Adapter\SanitizerPurifier` | construit par `defaultPolicies(media_hosts)` ; alias `Phil\Markup\HtmlPurifier` avec `purifier: sanitizer` |
| `phil_markup.purifier`, alias `Phil\Markup\HtmlPurifier` | construit par `Purifiers::defaultPolicies(media_hosts)`, ou `AmmoniaPurifier::defaultPolicies(media_hosts)` avec `purifier: ammonia` ; absent avec `purifier: sanitizer` |
| `phil_markup.smart_punctuation` | `SmartPunctuationCompiler`, qui décore `Phil\Markup\MarkupCompiler` ; avec `phil/typography`, sauf si `smart_punctuation` est désactivé |
| `phil_markup.typographers` | `Phil\Typography\Typographers` de la ponctuation soignée, quand `phil/typography-bundle` n'en enregistre aucun |
| `Phil\Markup\Markup` | public |
| `Phil\MarkupBundle\Infrastructure\Format\MarkupFormats` | public ; `formats`, `default_format`, `default_level`, `levels`, les libellés de `added_formats` |
| `Phil\MarkupBundle\Infrastructure\Conversion\UjoConverter` | public ; lit avec `DefaultCompiler` |
| `Phil\MarkupBundle\Infrastructure\Form\MarkupFormatType` | avec Symfony Form, étiqueté `form.type` |
| `Phil\MarkupBundle\Infrastructure\Twig\MarkupExtension` | avec Twig |

## Traductions

`translations/phil_markup.en.php` et `translations/phil_markup.fr.php` : les libellés des formats.

Les formats, les niveaux, les analyseurs et les adaptateurs sont ceux de `phil/markup`, le moteur de rendu celui de
`phil/ujo` : voir leurs références.
