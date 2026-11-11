# Extension points

What an application configures, replaces or relies on. Resources are `phil/resource` references, and slugs are
`phil/slug`'s.

## Configuration

| Option | What it extends |
|---|---|
| `slugger` | The service id of the slugger that makes tag slugs and category keys: a named slugger of `phil/slug-bundle`, `phil_slug.slugger.tags`, or a decorated one; `null` for the transliterating slugger |
| `fallback_locale` | The language of a category name when there is none in the language asked |

See [Tags](tags.md) and [Categories](categories.md).

## The application's resources

Any resource is tagged or categorised by its reference — `article`, `media.image`, the kinds the application chooses —
and `taggedWith()` reads them back by kind: the taxonomy knows nothing else of them. A bundle that tags its own
resources, as `phil/calendar-bundle` does its events, picks a kind of its own.

## `TaxonomyRepository`

`Phil\TaxonomyBundle\Domain\Repository\TaxonomyRepository` — tags, categories, translations and assignments, a public
alias of `DoctrineTaxonomyRepository`. A store of the application's own keeps the batch methods — `tags()`,
`categoriesWithIds()`, `translationsOf()` — reading many at once, and `branchIds()` the whole branch in one read, so
that moves stay free of cycles without reading the tree node by node.

## Services and forms

| Point | How |
|---|---|
| `Taxonomy` | Injected to find or create, rename, move and merge, and to tag resources |
| `TagsType` | Tags as text, separated by commas |
| `CategoryType` | A category chosen from the tree |
| More than a field does | A form type extension of the application's on either type — see [Forms](forms.md) |
