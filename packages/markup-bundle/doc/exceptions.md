# Exceptions

| Exception | Thrown when |
|---|---|
| `Phil\MarkupBundle\Infrastructure\Format\UnavailableFormat` | A filter, the converter or `MarkupFormats::format()` is asked for a format that is unknown or not in `formats` |
| `Phil\Markup\Exception\InvalidSource` | A UJO source given to `markup_html`, `markup_ujo` or the converter is not valid |
| `Phil\Ujo\Exception\InvalidDocument` | The converter cannot write a document as JSON |
| `ValueError` | A level given by its value is not `basic`, `forum` or `all` |

`UnavailableFormat` extends `InvalidArgumentException` and implements `Phil\Markup\Exception\MarkupException`, so
that catching the library's exceptions catches it too. Its message names the format and the enabled ones:

```text
The format "html" is not one of the enabled formats: markdown, bbcode, text.
```

A configuration that names an unknown format or level, a default format not enabled, or an added format whose name is
taken or not a name, is refused when the container is built, with Symfony's `InvalidConfigurationException`.
