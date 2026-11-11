# Exceptions

Every exception implements `Phil\Typography\Exception\TypographyException`, and each extends
`InvalidArgumentException`.

| Exception | Thrown when |
|---|---|
| `InvalidLocale` | A string that is not a language, an optional script and an optional region is read as a locale |
| `UnsupportedLanguage` | Hyphenation patterns are asked for a language the library has none for — Thai, which takes word breaks instead, Zulu —, or whose file cannot be read |
| `UnknownFixer` | A preset is asked for a fixer name it does not know |

The messages name what is wrong, for example:

```
"fr-ch-1901" is not a locale: write a language, then an optional script and region, as "fr", "fr_CA" or "sr_Latn_RS".
There are no hyphenation patterns for "th".
There is no fixer named "kerning"; the fixers are: unicode_normalization, ellipsis, dimension, dash, smart_quotes, curly_quote, no_space_before_comma, space_before_punctuation, trademark, unit, hyphen, word_break.
```

Fixing text never throws: a typographer that was built fixes any string.
