# Testing

| Area | What is covered |
|---|---|
| `PhilMarkupBundle`, `MarkupServices` | The services, their aliases and arguments; the formats, a format named twice, a later file replacing an earlier one, the default format and one not enabled; the levels, a level or format refused; the media hosts and those refused; the purifier chosen, and `ammonia` refused without its extension; the three ceilings, their defaults, the services and parameters they reach, a value that is not a positive integer refused |
| `MarkupFormats`, `UnavailableFormat` | The enabled formats, the default one, a format unknown or not enabled, the levels lowered to each ceiling, the format of an add-on and its label |
| `UjoConverter` | Every enabled format into a document and its JSON, the metadata, pretty JSON, raw HTML up to the ceiling, a format not enabled, a UJO source not valid |
| `MarkupFormatType` | The enabled formats offered in order with their labels, a format submitted, one not enabled, a label for every format, the format of an add-on with its label |
| `MarkupExtension` | The three filters, their defaults, the ceilings, `null`, a format not enabled |
| `MediaHostsPass` | The hosts, and the ceiling of an HTML purified, given to an adapter another bundle registered and to each factory of the default policies, a purifier built otherwise left alone, `HtmlPurifier` given back to the purifier chosen |
| `MaxBytesPass` | The ceilings given to a compiler another bundle registered, its other arguments kept, the compiler built refusing a longer source in a written format and in UJO; a compiler given its ceilings by position, built by a factory or of another class left alone; nothing without both parameters |
| A whole application | FrameworkBundle with forms and a French translator, and TwigBundle: a template that compiles BBCode at two levels, purifies a stored text and writes UJO; the converter; the form type rendered with its French labels |

```sh
vendor/bin/phpunit packages/markup-bundle/tests
```

## Benchmarks

`MarkupExtensionBench` runs the three Twig filters on Markdown and BBCode, from a comment to a long post. From the
monorepo root:

```sh
composer bench -- packages/markup-bundle/tests/Benchmark
```
