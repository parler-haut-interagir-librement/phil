# Extension points

The library is plain PHP and declares no service. Its one port is the slugger, and it is changed by decoration.

## `Slugger`

`Phil\Slug\Slugger` — turns a text into a `Slug`. See [Reference](reference.md#slugger).

| Method | Contract |
|---|---|
| `slug(string $text): Slug` | A valid slug, whatever the text; `InvalidSlug` when the text holds no letter or digit a slug can be made of |

| Point | How |
|---|---|
| Transliteration and length | `new TransliteratingSlugger($locale, $maxLength)` |
| Changing what a slugger gives | A decorator: a class implementing `Slugger` that receives the slugger it wraps, calls it, and changes the text before or the slug after; `LeadingWordsRemoved` is one |
| One slugger per use | `Sluggers`, a slugger by name and a default one: titles, file names, directory entries each get theirs |

`Slug::fromString()` checks and never fixes: a decorator returns a slug built by the slugger it wraps, or one that
already follows the rules.
