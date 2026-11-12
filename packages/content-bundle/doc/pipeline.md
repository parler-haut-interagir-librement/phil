# The pipeline: data mappers, mergers, normalizers

Three tagged stages read and write the capabilities of a row. Each stage is one service per capability, and a
collecting service runs them all.

| Stage | Interface | Tag | Run by | Does |
|---|---|---|---|---|
| 1 | `Application\Pipeline\DataMapper` | `phil_content.data_mapper` | `ContentDataMapper` | writes submitted data onto the unlocalized and localized draft rows |
| 2 | `Application\Pipeline\Merger` | `phil_content.merger` | `ContentAggregator`, `DimensionContentCopier` | copies one capability from a stored row onto a merged or copied row |
| 3 | `Application\Pipeline\Normalizer` | `phil_content.normalizer` | `ContentNormalizer` | turns one capability of a merged row into plain data |

The bundle tags every service implementing these interfaces by autoconfiguration, and tags its own services the
same way, so the application's stages run beside the bundle's. The tag names are the constants
`PhilContentBundle::DATA_MAPPER_TAG`, `MERGER_TAG` and `NORMALIZER_TAG`.

```text
submitted data ──DataMapper──▶ draft rows (*, fr) ──Merger──▶ merged row ──Normalizer──▶ array
                                                    (aggregate)
```

## Data mappers

```php
interface DataMapper
{
    /** @param array<string, mixed> $data the submitted values, already validated by the application */
    public function map(DimensionContent $unlocalized, DimensionContent $localized, array $data): void;
}
```

`ContentDataMapper::map($unlocalized, $localized, $data)` runs every data mapper over the two draft rows of the
dimension being edited. The caller loads or creates the rows and persists them afterwards; `ContentEditor` does
both ([publication.md](publication.md)). Each mapper handles one capability, ignores rows that lack it, and
decides which of its values are shared and which are per locale. The order of the mappers does not matter, since
no two write the same values. **A value one mapper refuses stops the whole write** before anything is persisted.

### Submitted data

The keys are those of the normalized form. **An absent key changes nothing; `null` clears its value**, and a
string that is empty once trimmed counts as `null`. That is what lets a form submit only the fields it shows.
Strings are trimmed, except `bodySource`. A value of the wrong type is refused with `InvalidContent`, naming its
key.

| Key | Accepted | Written on | By |
|---|---|---|---|
| `templateKey` | a string or `null` | unlocalized row | `TemplateDataMapper` |
| `templateData` | a map of field names (strings) to values, or `null` for no field; replaced whole | localized row | `TemplateDataMapper` |
| `seoTitle`, `seoDescription` | a string or `null` | localized row | `SeoDataMapper` |
| `seoKeywords` | a list of strings, one comma-separated string, or `null` (the empty list); items trimmed, empty ones dropped | localized row | `SeoDataMapper` |
| `seoCanonicalUrl` | a string (an absolute `http`/`https` URL) or `null` | localized row | `SeoDataMapper` |
| `seoNoIndex`, `seoNoFollow`, `seoHideInSitemap` | a boolean or `null` | localized row | `SeoDataMapper` |
| `excerptTitle`, `excerptDescription` | a string or `null` | localized row | `ExcerptDataMapper` |
| `excerptImage` | a string or `null` | unlocalized row | `ExcerptDataMapper` |
| `link` | `{url}`, `{resourceKey, resourceId, locale?}`, or `null`; an array naming both kinds, or neither, is refused | localized row | `LinkDataMapper` |
| `slug` | a string or `null` | localized row | `RoutableDataMapper` |
| `bodySource` | a string, kept as written, or `null`; blank clears the body | localized row | `BodyDataMapper` |
| `bodyFormat` | `markdown` (when absent), `html`, `bbcode`, `mediawiki`, `dokuwiki`, `creole`, `text` or `ujo`, the values of `phil/markup`'s `MarkupFormat`, or the name of a format an add-on brings to the compiler; read only with `bodySource`; a format the compiler does not know, and a UJO source that is not a valid document, are refused | localized row | `BodyDataMapper` |

There is no data mapper for `WorkflowCapable`: the place and the publication date are written by the workflow
and the publisher, never by submitted data.

`SubmittedData` holds the readers the data mappers use — `has()`, `string()`, `untrimmedString()`, `bool()`,
`stringList()`, `map()` — and an application data mapper may use them to follow the same rules.

## Mergers

```php
interface Merger
{
    public function merge(DimensionContent $target, DimensionContent $source): void;
}
```

`ContentAggregator` asks the root for a fresh row (`createDimensionContent()`), then runs every merger over the
unlocalized row first and the localized row second, so **a value the localized row sets wins** over the shared
one. **Merging never writes onto a stored row.** A merger copies only what its source actually sets, and ignores
rows that lack its capability.

| Merger | Rule |
|---|---|
| `TemplateMerger` | the key when the source sets one; the field values over the target's, key by key (`array_replace`, shallow) |
| `WorkflowMerger` | the place and the publication date, each when set |
| `SeoMerger` | each value when set: a text or URL when not `null`, the keywords when not empty, a directive when `true` or `false` |
| `ExcerptMerger` | title, description and image, each when set |
| `LinkMerger` | the link, whole, when set |
| `RoutableMerger` | the slug, when set |
| `BodyMerger` | the body, whole, when set |

`DimensionContentCopier::copy(DimensionContent $source, DimensionKey $dimension)` uses the same mergers to copy a
row onto a fresh row of another dimension: publishing copies a draft row to its live twin, forking copies a
version to the next. A fresh row has no value, so every capability that has a merger is copied whole; **a
capability without a merger is not copied**, and is not read through the aggregator either.

## Normalizers

```php
interface Normalizer
{
    /** @return array<string, mixed> */
    public function normalize(DimensionContent $content): array;
}
```

`ContentNormalizer::normalize($row)` runs every normalizer over one merged row and combines their fragments key
by key. **Two normalizers writing the same key is refused** with a `LogicException` rather than resolved by
order: the second would silently hide the first, and which one wins would depend on the order of the services
in the container. A normalizer returns an empty array for a row that lacks its capability.

### The normalized form

| Key | Value | From |
|---|---|---|
| `templateKey` | string or `null` | `TemplateNormalizer` |
| `templateData` | the field values as stored | `TemplateNormalizer` |
| `workflowPlace` | `unpublished`, `draft`, `published` or `null` | `WorkflowNormalizer` |
| `workflowPublished` | RFC 3339 string with its offset, or `null` | `WorkflowNormalizer` |
| `seoTitle`, `seoDescription` | string or `null` | `SeoNormalizer` |
| `seoKeywords` | list of strings | `SeoNormalizer` |
| `seoCanonicalUrl` | string or `null` | `SeoNormalizer` |
| `seoNoIndex`, `seoNoFollow`, `seoHideInSitemap` | boolean, `false` when no row set it | `SeoNormalizer` |
| `excerptTitle`, `excerptDescription` | string or `null` | `ExcerptNormalizer` |
| `excerptImage` | the reference as stored, or `null` | `ExcerptNormalizer` |
| `link` | `null`, or `{resourceKey, resourceId, locale, url}` with the keys of the other kind set to `null` | `LinkNormalizer` |
| `slug` | string or `null` | `RoutableNormalizer` |
| `bodySource` | string or `null`: the author's text on a draft row, the UJO JSON on a live row | `BodyNormalizer` |
| `bodyFormat` | a value of `MarkupFormat` on a draft row, `ujo` on a live row, or `null` | `BodyNormalizer` |
| `bodyHtml` | the stored HTML **purified again on this read**, or `null` | `BodyNormalizer` |
| `bodyRawHtml` | whether the author of the body held the raw HTML permission, or `null` without a body; a data mapper never reads it back | `BodyNormalizer` |

The normalized form is what a template receives as `content`, what `ContentManager::draft()` and `live()`
return, and the shape the data mappers read back: a normalized `link` can be submitted as it is.

## Example: reading a draft by hand

```php
use Phil\ContentBundle\Domain\Dimension\{DimensionKey, Stage};
use Phil\LocaleDimensions\{Locale, Version};

$row = $aggregator->aggregate($root, DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first()));
$data = $normalizer->normalize($row);   // ['excerptTitle' => '…', 'bodyHtml' => '…', …]
```

`ContentManager::draft()` and `live()` do this for you, picking the right version
([content-manager.md](content-manager.md)).
