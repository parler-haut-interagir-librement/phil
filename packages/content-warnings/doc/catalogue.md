# Catalogue

A `Catalogue` lists the themes content can be warned for, grouped in categories, in the order they were defined.

## Keys and labels

Every theme and category has a **key** — lowercase ASCII letters and digits in words joined by single hyphens, 64
characters at most, `self-harm` — and a **label** in one language or more. `Key::isValid()` tells a valid key. Keys
are stable: warnings and preferences name them, while labels may be reworded freely.

A `LocalizedText` holds a text by locale. A locale is a language code of two or three lowercase letters, optionally
followed by up to three subtags, `fr_CA` or `yue_Hant_HK`; a hyphen is read as an underscore. Texts are trimmed,
empty ones dropped, and 500 characters at most. `in($locale, $fallbacks)` reads the text in the locale, then in its
language (`fr` for `fr_CA`), then in the first fallback that has one (`['en']` by default), then the first text given.

| Type | Holds |
|---|---|
| `Category` | `key`, `label`; `label($locale, $fallbacks)` |
| `Theme` | `key`, `category` (a category key), `label`, `description` (possibly empty); `label()` and `description()` by locale |
| `Catalogue` | `theme($key)`, `has($key)`, `category($key)`, `themes()`, `categories()`, `themesIn($category)` |

A catalogue refuses a key given twice, a theme in a category it does not have, and more than 200 categories or 2,000
themes, with `InvalidCatalogue`.

## The base list

`BaseCatalogue::load()` reads the list shipped in `resources/base-catalogue.php`: about seventy themes in twelve
categories — violence, sexuality, abuse, death and loss, mental health, substances and addiction, discrimination, body
and medicine, phobias, animals, sensory, other themes — each labelled in English, French, Spanish, German, Italian and
Portuguese. Loading it takes well under a millisecond; an application loads it once and keeps the catalogue, which is
immutable.

## Definitions

`CatalogueLoader::apply($definition, $onto)` builds a catalogue from a definition, or applies the definition onto an
existing catalogue and returns a new one:

```php
$catalogue = new CatalogueLoader()->apply([
    'categories' => [
        'venues' => ['label' => ['en' => 'Venues', 'fr' => 'Lieux']],
    ],
    'themes' => [
        'crowds' => ['category' => 'venues', 'label' => ['en' => 'Crowds', 'fr' => 'Foules']],
        'gore' => ['label' => ['en' => 'Graphic gore']],
    ],
    'remove' => ['clowns'],
], BaseCatalogue::load());
```

| Key | Effect |
|---|---|
| `replace` | `true` starts from an empty catalogue instead of the one given |
| `categories` | Categories by key, each with its `label`: added, or merged into an existing one |
| `themes` | Themes by key, with `category`, `label` and `description`: added, or merged into an existing one |
| `remove` | Theme keys taken out of the catalogue |
| `remove_categories` | Category keys taken out, once none of their themes is left |

The steps run in that order. A new category or theme comes after the existing ones; an existing one keeps its place.
Labels and descriptions are merged by locale, the definition winning; a theme's category is replaced when given. A
new theme needs a category and a label.

Each step is checked. An unknown key, an entry that is not an array, a theme without category or label, a key to
remove that is not there, or a category removed with themes left throws `InvalidCatalogue`, and nothing of the
definition is applied.

`applyFile($path, $onto)` applies the definition a PHP file returns or a JSON file holds, 2 MB at most; JSON is read
16 levels deep at most.
