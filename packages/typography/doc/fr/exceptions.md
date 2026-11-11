<!-- phil-dev source-sha256=746927773461247000a0055e6717dd7e4372c05b26032b8ba6f7ecae6e3c782a -->
# Exceptions

Chaque exception implémente `Phil\Typography\Exception\TypographyException`, et chacune étend
`InvalidArgumentException`.

| Exception | Lancée quand |
|---|---|
| `InvalidLocale` | Une chaîne qui n'est pas une langue, suivie d'une écriture et d'une région facultatives, est lue comme une locale |
| `UnsupportedLanguage` | Des motifs de césure sont demandés pour une langue dont la bibliothèque n'a aucun (le thaï, qui reçoit plutôt des coupures de mots, le zoulou), ou dont le fichier ne peut pas être lu |
| `UnknownFixer` | On demande à un préréglage un nom de correcteur qu'il ne connaît pas |

Les messages nomment ce qui ne va pas, par exemple :

```
"fr-ch-1901" is not a locale: write a language, then an optional script and region, as "fr", "fr_CA" or "sr_Latn_RS".
There are no hyphenation patterns for "th".
There is no fixer named "kerning"; the fixers are: unicode_normalization, ellipsis, dimension, dash, smart_quotes, curly_quote, no_space_before_comma, space_before_punctuation, trademark, unit, hyphen, word_break.
```

Corriger du texte ne lance jamais d'exception : un typographe construit corrige n'importe quelle chaîne.
