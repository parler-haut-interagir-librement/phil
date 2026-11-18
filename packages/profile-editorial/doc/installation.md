# Installation

## Stability

The members are alpha releases, published as `0.x.y-alpha`. Composer installs them in a project that accepts that
stability; `prefer-stable` keeps every other package at its stable release when there is one:

```sh
composer config minimum-stability alpha
composer config prefer-stable true
composer require phil/profile-editorial
```

The members require Symfony 8.2 or later: while 8.2 has no stable release, the same settings let Composer install its
beta or release candidate.

A stability flag on the requirement alone, `phil/profile-editorial:@alpha`, is not enough: Composer applies flags to
the packages the project names, never to the members a metapackage requires.

## Registering the bundles

With Symfony Flex, each bundle is added to `config/bundles.php` as it is installed. Without Flex, add them:

```php
// config/bundles.php
return [
    // …
    Phil\MarkupBundle\PhilMarkupBundle::class => ['all' => true],
    Phil\ContentWarningsBundle\PhilContentWarningsBundle::class => ['all' => true],
    Phil\StructuredDataBundle\PhilStructuredDataBundle::class => ['all' => true],
];
```

`phil/citations` and `phil/footnotes-sidenotes` are libraries: the application builds their services where it needs
them, with no bundle.

## What the application brings

| Package | Why |
|---|---|
| `symfony/twig-bundle` | The filters `markup_html`, `markup_purify` and `markup_ujo`, the function `structured_data()`, and the folding template of the content warnings. |
| `symfony/form` | The fields `MarkupFormatType`, `ContentWarningsType` and `WarningPreferencesType`. |
| `doctrine/dbal` | The column type `phil_content_warnings`, when warnings are stored with an entity. |
| StimulusBundle with AssetMapper | The controller `reveal` that unfolds a warned content. |

## What to configure next

Every member works with its defaults once registered. The usual next steps:

1. Choose the formats and purification levels authors may use in `phil_markup`, and render their text with
   `markup_html`.
2. Describe the site and its owner in `phil_structured_data`, and print `{{ structured_data() }}` at the end of the
   base template.
3. Add a `phil_content_warnings` column to the content that needs warnings, and show it through
   `@PhilContentWarnings/fold.html.twig`.
4. Resolve the notes of a UJO document with `NoteResolver`, and its references with `CitationProcessor`.

The [members](members.md) page links the documentation of each one.
