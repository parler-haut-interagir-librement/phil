# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Add a citation style, such as a processor of CSL style files the application provides | Implement `Contract\CitationStyle` |
| Write a style in PHP with the helpers of the library's styles | Extend `Style\AbstractStyle` |
| Cite in another language | Build a `Terms` with its words |
| Read larger or smaller files | Pass `Import\ImportLimits` to the readers, `maxBytes` and `maxItems` to `CslJson` |
| Bring references from elsewhere | Build `Model\Reference` objects, or CSL-JSON, and give them to a `ReferenceList` |

A `CitationStyle` says how it orders its bibliography (`BibliographyOrder::Alphabetical` or `Citation`), whether it
wants year letters, and writes a citation from its `CitedItem`s — each with its `Entry` (reference, number, year
letter), its `Cite` and whether it is the first cite of the reference — and an entry from its `Entry`. The processor
does the rest.

```php
use Phil\Citations\Contract\CitationStyle;
use Phil\Citations\RichText;
use Phil\Citations\Style\BibliographyOrder;
use Phil\Citations\Style\CitedItem;
use Phil\Citations\Style\Entry;
use Phil\Citations\Terms;

final class Keys implements CitationStyle
{
    public function order(): BibliographyOrder { return BibliographyOrder::Citation; }
    public function yearSuffixes(): bool { return false; }

    public function citation(array $items, Terms $terms): RichText
    {
        return RichText::text('['.implode('; ', array_map(static fn (CitedItem $item): string => $item->entry->reference->id, $items)).']');
    }

    public function entry(Entry $entry, Terms $terms): RichText
    {
        return RichText::concat('['.$entry->reference->id.'] ', RichText::italic((string) $entry->reference->title()));
    }
}
```

The package holds no style file of a third party: an application that wants CSL styles provides the files and the
style that reads them. The classes under `Internal` are not extension points.
