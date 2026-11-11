<!-- phil-dev source-sha256=f68c9d1e466d453362b3dcca46acac6dc4a68613d180c5ec625e6d7689fac24e -->
# Exceptions

Chaque exception implémente `Phil\Highlight\Exception\HighlightException`, et chacune étend
`InvalidArgumentException`.

| Exception | Levée quand |
|---|---|
| `UnknownLanguage` | On demande à `language()`, `tokenize()` ou `toHtml()` un nom que le colorateur ne connaît pas |
| `InvalidLanguage` | On donne à `with()` ou `of()` un langage dont le nom ou un alias ne peut pas être demandé |
| `InvalidLineRanges` | On donne à `LineRanges::parse()` ou `of()` des lignes qu'il ne lit pas, ou à `LineOptions` un premier numéro sous 0 ou au-dessus de 1 000 000 |

Les messages nomment ce qui ne va pas :

```
There is no language named "cobol"; the languages are: bash, c, cpp, csharp, css, diff, dockerfile, go, html, ini, java, javascript, json, markdown, php, python, ruby, rust, scss, sql, twig, typescript, xml, yaml.
"Lua" cannot name a language: write lower-case letters, digits and "_ + # . -", 32 at most.
"1,,2" are not line ranges: write lines and ranges from 1 separated by commas, such as "1,3-5".
The range 5-3 ends before it starts.
```

`highlight()` ne lève jamais d'exception : il retourne `null` pour un langage inconnu. L'analyse lexicale ne lève
jamais d'exception : une source qui n'est pas de son langage revient avec moins de couleurs.
