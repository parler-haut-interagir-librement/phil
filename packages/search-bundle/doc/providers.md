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

`index_entities` says how the indexes learn of a flush:

| Mode | Default with | What happens |
|---|---|---|
| `outbox` | `meilisearch`, `loupe` | The flush's changes are recorded in `phil/outbox-bundle`'s table, in the flush's transaction, and indexed once the relay delivers them |
| `direct` | `dbal` | `IndexOnFlushListener` indexes the flush's changes once it succeeds, in the same request |
| `none` | — | Nothing is indexed at a flush; the application calls `Search` itself |

`true` is the default of the adapter, `false` is `none`.

### Through the outbox

An index outside the database cannot join the database's transaction. Written after the commit, as `direct` does, it
misses what a flush committed while the engine was down, and a flush inside a transaction the caller rolls back later
has already reached it. `OutboxIndexingListener` records instead, in the flush's transaction, on the connection of the
entities:

- each entity a provider knows that the flush inserts or updates, by its class and its identifier;
- each document of an entity the flush deletes, by its index and its identifier there, read before the row is deleted.

The records commit with the rows, or not at all. The relay of `phil/outbox-bundle` — `phil:outbox:relay`, its
schedule or the Messenger bridge — rebuilds them through `SearchOutboxCodec` and dispatches them; `OutboxIndexer` reads
the entity again and indexes it as it then is, nothing when it is gone, or removes the document. An engine down fails
the delivery, which the relay tries again after its delay, the content saved meanwhile. The records of one entity share
an ordering key, so its indexing and its removal go in the order they happened, one per batch of the relay.

```yaml
# config/packages/phil_outbox.yaml
phil_outbox:
    connection: default      # the connection of the entities phil_search indexes
```

- **The outbox on the same connection.** `phil_outbox.connection` is the connection of `phil_search.connection`: the
  records join the flush's transaction only there.
- **Identifiers only.** A record carries the entity's class and identifier, never a field of its document. An
  identifier is a string or an integer, an enum or a stringable value, without `@` or whitespace; another refuses the
  flush, and that entity is indexed with `index_entities: direct`.
- **One document per delivery.** Each record goes to the index on its own: a large import indexes faster with
  `phil:search:reindex` afterwards.
- Without `phil/outbox-bundle`, `outbox` — the default with Meilisearch and Loupe — is refused when the container is
  built; install it, or choose `direct`.

### After the flush

With `index_entities: direct`, `IndexOnFlushListener` indexes every entity a flush inserts or updates, and removes
every entity it deletes, once the flush succeeds. A deleted entity's place in the indexes, `Search::addressesOf()`, is read before
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
