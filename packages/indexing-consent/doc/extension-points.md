# Extension points

The library is plain PHP and declares no service. What an application writes is the contract on its public records;
what reads them asks the policy.

## `HasIndexingConsent`

`Phil\IndexingConsent\Contract\HasIndexingConsent` — a public record whose reach the person it is about decides.

| Method | Contract |
|---|---|
| `indexing(): Indexing` | The choice of the person, as kept on the record. Cheap and without I/O: a reader asks it for every record it walks. |

Keep the choice as a column of the record, its `Indexing` value, `listed` by default. When two choices apply — the
person's, and the site's for a kind of page —, answer with `Indexing::strictest()` of both.

```php
#[ORM\Column(enumType: Indexing::class)]
private Indexing $indexing = Indexing::Listed;

public function indexing(): Indexing
{
    return Indexing::strictest($this->indexing, $this->directory->indexing());
}
```

## Asking it

A reader never reads the column itself: it asks `IndexingPolicy`, which also answers for the records that do not
implement the contract.

- **The sitemap** keeps the pages `IndexingPolicy::filter($records, Reader::Sitemap)` gives.
- **The structured data** of a page describes its subject only when `IndexingPolicy::allows($record,
  Reader::StructuredData)`.
- **The page** sends `IndexingPolicy::of($record)->robots()`, when there is one, as `<meta name="robots">` or
  `X-Robots-Tag`.
- **The site's listings** — a directory, a search, a page of records — keep what `Reader::SiteListings` allows.
- **A query** that filters on the stored value keeps `Indexing::allowing($reader)`, rather than loading every record
  to ask it.
- **A page that shows several records** is as narrow as `IndexingPolicy::ofAll(...$records)`.

## The request of a page

The controller of a page that shows a record sets the record's `Indexing` on the request, under
`IndexingPolicy::REQUEST_ATTRIBUTE`. The other readers of the page — its structured data, a listener that writes the
robots directives — read that attribute rather than knowing the record. A reader that finds a value it does not
recognise reads it as `Unlisted`, the narrowest: an error never puts a withdrawn record back in sight.

```php
$request->attributes->set(IndexingPolicy::REQUEST_ATTRIBUTE, $listing->indexing());
```

A change of choice reaches every reader at its next read; a cache of a listing, a sitemap or a page is invalidated
with the record.
