# Categories

A category has a key — a slug, unique —, a parent or none, a position among its siblings, names and descriptions by
language, and a path from the root, `/a/b/c/`, by which a whole branch is found at once. A category is at depth 10 at
most, a root being at depth 0: a tree holds eleven levels, and the path of the deepest category fits its 420-character
column.

| Method | What it does |
|---|---|
| `createCategory($key, $parent, $names, $position)` | A new category; a key taken is an `InvalidTaxonomy` |
| `translate($category, $locale, $name, $description)` | Its name in a language, created or changed |
| `name($category, $locale)` | `fr_CA`, else `fr`, else the fallback language, else the key |
| `names($categories, $locale)` | The names of several categories, by identifier, their translations read in one query: those of the language, its base language and the fallback language only |
| `move($category, $parent, $position)` | Moves it with its branch; under itself or its branch, or a branch whose deepest category would pass depth 10, is an `InvalidTaxonomy` |
| `tree($locale)` | `CategoryNode`s — category, name, children —, siblings by position then key; two queries, whatever the size of the tree |
| `categorize($resource, $categories)`, `categoriesOf($resource)` | Gives a resource exactly these categories; its categories |
| `categorizedIn($category, $withDescendants, $kind)` | The resources in a category and, unless told otherwise, its whole branch |
| `mergeCategories($from, $into)` | Moves the first's children and resources to the second, then removes it with its names; the tree is read once and the children are written in one flush, whatever their number |

A category with children cannot be deleted from the database — its children's `parent` restricts it —: merge it,
or move its children first.
