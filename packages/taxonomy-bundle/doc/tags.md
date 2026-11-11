# Tags

A tag is a name of at most 100 characters and its slug — `Jeux de rôle` is `jeux-de-role` —, unique: two names that
differ only by case or accents are one tag.

The slug is made by the taxonomy's slugger: by default the transliterating slugger of `phil/slug`, at most 128
characters, cut at a word. The `slugger` key names another — a named slugger of `phil/slug-bundle`
(`phil_slug.slugger.tags`), or a decorated one — and the same slugger makes category keys. A slug longer than 128
characters, or a name with nothing to make a slug of, is an `InvalidTaxonomy`.

| Method | What it does |
|---|---|
| `tag($name)` | The tag of that name, created when new |
| `setTags($resource, $names)` | Gives the resource exactly these tags, at most `Taxonomy::MAX_TAGS` (100), or `InvalidTaxonomy` before any query; returns them by name |
| `tagsOf($resource)` | Its tags, by name |
| `taggedWith($tag, $kind)` | The resources with that tag, of one kind when given |
| `renameTag($tag, $name)` | Renames it; another tag of that name is an `InvalidTaxonomy`: merge them instead |
| `mergeTags($from, $into)` | Gives the second every resource the first has, once, then removes the first |

```php
$taxonomy->setTags(ResourceReference::of('page', '42'), ['Jeux', 'soirée']);
$taxonomy->mergeTags($taxonomy->tag('jdr'), $taxonomy->tag('rpg'));
```
