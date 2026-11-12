# Providers

A `DocumentProvider` turns one kind of resource into the documents of one index; with autoconfiguration, the bundle
finds it.

```php
final readonly class PageDocuments implements DocumentProvider
{
    public function index(): string { return 'pages'; }

    public function supports(object $resource): bool { return $resource instanceof Page; }

    public function document(object $resource): ?Document
    {
        return $resource->isPublished()
            ? Document::of('pages', $resource->id, ['title' => $resource->title, 'body' => $resource->body], ['locale' => $resource->locale, 'tags' => $resource->tags])
            : null;   // a draft must not be found
    }

    public function id(object $resource): string { return $resource->id; }

    public function all(): iterable { return $this->pages->findAll(); }
}
```

A document has an index, an identifier — letters, digits, `_`, `-` —, text fields searched and filter fields, each a
scalar or a list of them. A `null` document removes the resource from the index.

## What a flush does

With `index_entities`, `IndexOnFlushListener` indexes every entity a flush inserts or updates, and removes every entity
it deletes, once the flush succeeds. A deleted entity's place in the indexes, `Search::addressesOf()`, is read before
the flush, while its identifier is known: Doctrine empties a generated identifier once the row is deleted. A flush
that fails closes its entity manager, and what it would have indexed is dropped with it; clearing the entity manager,
or the container's `kernel.reset` between a worker's messages, drops it too.

The entities of a flush go to the index together: their documents in one `SearchIndex::index()` call — one
transaction of the database table, one request to Meilisearch per index —, and their removals in one call per index.
The order of the changes holds: entities noted after a deletion are indexed after it.

For anything else, call `Search::index(...$resources)`, which sends the documents of every resource given at once,
`Search::remove()`, `Search::removeAt($addresses)`, or `Search::forget($index, ...$ids)`.

## Rebuilding

`Search::reindex()` and `phil:search:reindex` rebuild an index from its providers, `batch` documents at a time. The
index answers searches all along, with the documents it held, until the rebuild is complete, and a rebuild that fails
leaves it as it was:

| Adapter | How |
|---|---|
| `dbal` | One transaction: other connections read the documents the table held until it commits |
| `meilisearch` | The documents go to `{index}__rebuild`, which takes the live index's settings; the two are swapped, then the old one deleted |
| `loupe` | The documents go to a new directory beside the live one, which `phil_search.json` then names |

A resource changed while a rebuild runs is indexed in the live index, and the rebuild may carry the version it read:
run the rebuild again, or let the next flush of that resource correct it. A provider that gives a document of another
index fails the rebuild.
