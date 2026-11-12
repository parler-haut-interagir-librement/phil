# Translation

| Type | Kind | Role |
|---|---|---|
| `Contract\Translatable` | interface | `translationClass()`, `translatedFields()`, `newTranslation()` |
| `Contract\Translation` | interface | `translatable()`, `locale()` |
| `TranslationEntity` | trait | The column `locale`, in the one spelling of `phil/locale-dimensions` |
| `TranslationListener` | listener: `loadClassMetadata` | Checks the mapping, adds the unique index on the record and the locale |
| `Translations` | service | `find()`, `get()`, `translate()`, `locales()`, `preload()`, `values()` |
| `TranslationSet` | value | The translations `preload()` read, by record |
| `Exception\InvalidTranslation` | exception | A translation entity mapped otherwise, or a translation made for another record |

A translated record keeps what every locale shares — a price, a date, an image — and its text lives on a translation
entity of its own, one row per record and locale, with only the translated columns. The locales, their spelling and
the fallback chains are those of `phil/locale-dimensions`.

```php
#[ORM\Entity]
class Product implements Translatable
{
    public static function translationClass(): string { return ProductTranslation::class; }
    public static function translatedFields(): array { return ['name', 'description']; }

    public function newTranslation(Locale $locale): ProductTranslation
    {
        return new ProductTranslation($this, $locale);
    }
}

#[ORM\Entity]
class ProductTranslation implements Translation
{
    use TranslationEntity;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    public string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $description = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Product::class)]
        #[ORM\JoinColumn(name: 'product_id', nullable: false, onDelete: 'CASCADE')]
        private Product $translatable,
        Locale $locale,
    ) {
        $this->writtenIn($locale);
    }

    public function translatable(): Product { return $this->translatable; }
}
```

```php
$translations = new Translations($manager);
$chain = $locales->chainFor(Locale::fromString('fr_CA'));   // fr_CA → fr → en

$translation = $translations->find($product, $chain);        // the fr_CA row, or fr, or en, or null
$translations->values($translation);                         // ['name' => '…', 'description' => '…']
```

## The mapping

The translation entity maps the to-one relation `translatable` to its record, held by one join column, and the field
`locale`, which `TranslationEntity` gives. As the mapping is loaded, `TranslationListener` refuses a translation entity
without either, with `InvalidTranslation`, and adds to its table a unique index on the join column and the locale,
named `<table>_translatable_locale`: one row per record and locale, the only thing that holds when two requests
translate the same record at once. An index the entity declares on the same two columns, in either order, is kept as
it is. A subclass inherits both from the class that maps them.

The record has no collection of its translations: a list of records would load every locale of each to show one.
`Translations` reads the rows it needs.

## Reading

| Method | Returns |
|---|---|
| `find($record, FallbackChain)` | the row of the first locale of the chain that has one, or `null` |
| `get($record, Locale)` | the row of exactly that locale, or `null` |
| `locales($record)` | the locales the record has a row in, ordered by their value: what `hreflang` alternates list |
| `preload(iterable $records, FallbackChain)` | a `TranslationSet`: `of($record)` gives each record's row along the chain |
| `values(Translation)` | the fields `translatedFields()` names, by name, in that order |

**A row is served whole.** The chain chooses a row, never a field: a French row with no description is served without
one, rather than with the English description beside a French name. A chain that runs out serves nothing, and the
caller decides what that means — a 404, a placeholder, a page that says the text is not translated.

**A list is one query per translation class.** `preload()` reads the rows of all the records, in all the locales of
the chain, with one query per translation class and per 500 records (`Translations::CHUNK`), then picks each record's
row in memory. Read the list first, preload, then render:

```php
$products = $repository->findBy(['category' => $category]);
$set = $translations->preload($products, $chain);

foreach ($products as $product) {
    $set->of($product)?->name;
}
```

Rows persisted and not flushed count, so a record translated in this request is read back before the flush; rows
scheduled for removal do not. A record that is not stored yet reads nothing from the database.

## Writing

`translate($record, Locale)` gives the row of exactly that locale, to be written: the stored one, or a new one made by
`newTranslation()` and persisted for the next flush. **It never copies a field from another locale.** The text a reader
would have been served through the chain is not the text of this locale, and a copy would pass for a translation that
nobody wrote, then drift from its source without anyone seeing it. An editor that wants to start from another locale
shows that locale beside the empty fields.

`newTranslation()` must make a translation of this record, of the class `translationClass()` names, in the locale
asked; anything else raises `InvalidTranslation`. The record and the locale of a row are given by its constructor and
never change.

## Slugs

A translation entity that is `Sluggable` takes `ScopedSluggableEntity` and names `locale` in `slugScope()`, so that
each locale has its own slugs: `/fr/crepes` and `/en/crepes` coexist, and two French rows titled alike get `crepes` and
`crepes-2`. `SlugListener` refuses the slug of a translation whose scope does not name its locale, with
`InvalidScope`. Declare the unique index on the scope and the slug:

```php
#[ORM\UniqueConstraint(columns: ['locale', 'slug'])]
class ProductTranslation implements Translation, Sluggable
{
    use TranslationEntity;
    use ScopedSluggableEntity;

    public function slugSource(): string { return $this->name; }
    public function slugScope(): array { return ['locale']; }
}
```

## Versions

There is no stage and no copy of a source locale: a translation that changes over time is `Versionable`, as any
record is, and each locale has its own versions — frozen, forked and restored on its own by `Versions`. The record's
shared columns are versioned on the record, if at all. See [Versions](versions.md).
