# Exceptions

Every exception implements `Phil\Highlight\Exception\HighlightException`, and each extends
`InvalidArgumentException`.

| Exception | Thrown when |
|---|---|
| `UnknownLanguage` | `language()`, `tokenize()` or `toHtml()` is asked for a name the highlighter does not know |
| `InvalidLanguage` | `with()` or `of()` is given a language whose name or alias cannot be asked for |
| `InvalidLineRanges` | `LineRanges::parse()` or `of()` is given lines it does not read, or `LineOptions` a first number below 0 or above 1,000,000 |

The messages name what is wrong:

```
There is no language named "cobol"; the languages are: bash, c, cpp, csharp, css, diff, dockerfile, go, html, ini, java, javascript, json, markdown, php, python, ruby, rust, scss, sql, twig, typescript, xml, yaml.
"Lua" cannot name a language: write lower-case letters, digits and "_ + # . -", 32 at most.
"1,,2" are not line ranges: write lines and ranges from 1 separated by commas, such as "1,3-5".
The range 5-3 ends before it starts.
```

`highlight()` never throws: it returns `null` for an unknown language. Tokenizing never throws: a source that is not
of its language comes back with fewer colours.
