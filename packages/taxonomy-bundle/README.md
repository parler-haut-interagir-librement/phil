# phil/taxonomy-bundle

**Tags and a translated category tree for any kind of resource, with form types to edit them.**

phil/taxonomy-bundle gives a Symfony application tags and a tree of categories. A tag is found or created by its name,
whatever its case or accents, then renamed or merged into another. A category has a key, a parent, a position among
its siblings, and names and descriptions by language. It moves with its whole branch, never under itself, and merges
into another. A resource is tagged and categorized by its kind and identifier, then found again by tag or by a whole
branch at once.

It is for an application whose editors sort pages, events or products, and whose visitors browse them by subject in
their own language. Two names that differ only by accents or case are one tag, so `Jeux de rôle` and `jeux de role`
never split a list in two. Merging cleans up duplicates without losing a single assignment.

The bundle stores its tables with Doctrine ORM and refers to resources through `phil/resource`, so a tag never needs a
foreign key to the tagged entity. Slugs and category keys come from `phil/slug`, or from a named slugger of
`phil/slug-bundle`. `TagsType` and `CategoryType` plug into Symfony Form: tags typed as text separated by commas, and
a category picked from the indented tree. Each category keeps its path from the root, which finds a whole branch in a
single query.

## Status

| Part | State |
|---|---|
| `Tag`, `Category`, `CategoryTranslation`, `Assignment`; the repository port | Implemented and tested |
| `Taxonomy`: tags, categories, the tree, assignments, merges | Implemented and tested |
| `DoctrineTaxonomyRepository` | Implemented and tested |
| `TagsType`, `CategoryType` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, configuration, the tables. |
| [Tags](doc/tags.md) | Finding, giving, renaming and merging tags. |
| [Categories](doc/categories.md) | The tree, names by language, moving, merging, categorizing. |
| [Forms](doc/forms.md) | `TagsType` and `CategoryType`. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```php
$page = ResourceReference::of('page', $page->id);
$taxonomy->setTags($page, ['Jeux', 'soirée']);

$games = $taxonomy->createCategory('games', null, ['fr' => 'Jeux', 'en' => 'Games']);
$taxonomy->categorize($page, [$games]);
$taxonomy->categorizedIn($games);   // the whole branch
```

## Requirements

PHP 8.5 or later with `ext-intl` and `ext-mbstring`; Doctrine ORM 3.7; Symfony 8.2's dependency injection, config,
form, options resolver and UID; `phil/slug` 0.1 for slugs and `phil/resource` 0.1 for resource references.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
