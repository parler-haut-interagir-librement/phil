# Exceptions

Every exception implements `Phil\Typography\Exception\TypographyException`, and each extends
`InvalidArgumentException`.

| Exception | Thrown when |
|---|---|
| `InvalidLocale` | A string that is not a language, an optional script and an optional region is read as a locale |
| `UnsupportedLanguage` | Hyphenation patterns are asked for a language the library has none for — Thai, which takes word breaks instead, Zulu —, or whose file cannot be read |
| `UnknownFixer` | A preset is asked for a fixer name it does not know |
| `InputTooLarge` | A fragment, a document or a text is longer than the ceiling of the typographer — `maxBytes`, 1 MiB by default —; `$bytes` and `$limit` say both lengths |

The messages name what is wrong, for example:

```
"fr-ch-1901" is not a locale: write a language, then an optional script and region, as "fr", "fr_CA" or "sr_Latn_RS".
There are no hyphenation patterns for "th".
There is no fixer named "kerning"; the fixers are: unicode_normalization, ellipsis, dimension, dash, smart_quotes, curly_quote, no_space_before_comma, space_before_punctuation, trademark, unit, hyphen, word_break.
The input is 1048577 bytes long, above the limit of 1048576 bytes.
```

Fixing text never throws on what a text says: a typographer that was built fixes any string up to its ceiling, and
refuses a longer one whole, before parsing it — see [The ceiling of a text](html.md#the-ceiling-of-a-text).
