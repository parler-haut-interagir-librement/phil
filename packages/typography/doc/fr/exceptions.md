<!-- phil-dev source-sha256=8afc729e230d6e5a61c0e72ab33bbed291c5b09f7c363e38ef3cfc5b119c8c92 -->
# Exceptions

Chaque exception implémente `Phil\Typography\Exception\TypographyException`, et chacune étend
`InvalidArgumentException`.

| Exception | Lancée quand |
|---|---|
| `InvalidLocale` | Une chaîne qui n'est pas une langue, suivie d'une écriture et d'une région facultatives, est lue comme une locale |
| `UnsupportedLanguage` | Des motifs de césure sont demandés pour une langue dont la bibliothèque n'a aucun (le thaï, qui reçoit plutôt des coupures de mots, le zoulou), ou dont le fichier ne peut pas être lu |
| `UnknownFixer` | On demande à un préréglage un nom de correcteur qu'il ne connaît pas |
| `InputTooLarge` | Un fragment, un document ou un texte est plus long que le plafond du typographe (`maxBytes`, 1 Mio par défaut) ; `$bytes` et `$limit` donnent les deux longueurs |

Les messages nomment ce qui ne va pas, par exemple :

```
"fr-ch-1901" is not a locale: write a language, then an optional script and region, as "fr", "fr_CA" or "sr_Latn_RS".
There are no hyphenation patterns for "th".
There is no fixer named "kerning"; the fixers are: unicode_normalization, ellipsis, dimension, dash, smart_quotes, curly_quote, no_space_before_comma, space_before_punctuation, trademark, unit, hyphen, word_break.
The input is 1048577 bytes long, above the limit of 1048576 bytes.
```

Corriger du texte ne lance jamais d'exception à cause de ce que dit un texte : un typographe construit corrige
n'importe quelle chaîne jusqu'à son plafond, et en refuse une plus longue en entier, avant de l'analyser ; voir
[Le plafond d'un texte](html.md#le-plafond-dun-texte).
