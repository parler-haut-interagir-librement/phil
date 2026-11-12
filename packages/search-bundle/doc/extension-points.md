# Extension points

What an application implements, replaces, configures or relies on.

## Ports

### `DocumentProvider`

`Phil\SearchBundle\Application\DocumentProvider` — turns one kind of resource into the documents of one index. A
service implementing it is tagged `phil_search.provider` by autoconfiguration. See [Providers](providers.md).

| Method | Contract |
|---|---|
| `index(): string` | The index its documents go to |
| `supports(object $resource): bool` | Whether it reads this resource |
| `document(object $resource): ?Document` | The resource's document, or `null` to keep it out of the index — a draft must not be found |
| `id(object $resource): string` | The resource's identifier in the index, read before a deletion empties it |
| `all(): iterable<object>` | Every resource of the index, for a rebuild; iterated, never loaded at once |

### `SearchIndex`

`Phil\SearchBundle\Application\SearchIndex` — where documents are indexed and searched. `DbalSearchIndex`,
`MeilisearchIndex`, or `LoupeIndex` of `phil/search-loupe`, by `adapter`; an application aliases the port to an engine
of its own.

| Method | Contract |
|---|---|
| `index(Document ...$documents): void` | Adds the documents, or replaces those with the same identifiers, all at once |
| `remove(string $index, list<string> $ids): void` | Removes those documents |
| `clear(string $index): void` | Empties the index |
| `rebuild(string $index, iterable<list<Document>> $batches): void` | Replaces every document, the index answering searches with what it held until the last batch is written; a rebuild that fails leaves it as it was |
| `search(string $index, SearchQuery $query): SearchResult` | A page of documents found, and the total |

### `SynonymProvider`

`Phil\SearchBundle\Application\Contract\SynonymProvider` — gives the synonyms of a language. A service implementing it
is tagged `phil_search.synonym_provider` by autoconfiguration. See [Synonyms](synonyms.md).

| Method | Contract |
|---|---|
| `rules(string $language): iterable<SynonymRule>` | The rules of a language, a primary subtag in lower case: `fr`; none for a language it does not know |

### `RejectedForms`

`Phil\SearchBundle\Application\Contract\RejectedForms` — the forms a vocabulary rejects for its preferred terms, read
as one-way synonyms by `RejectedFormSynonyms`; its services are listed under `synonyms.rejected_forms`.

| Method | Contract |
|---|---|
| `rejectedForms(string $language): iterable<array{string, string}>` | Each rejected form and its preferred term |

### `SynonymAwareIndex`

`Phil\SearchBundle\Application\Contract\SynonymAwareIndex` — implemented by an index that applies synonyms itself.
`Search` then sends it the words as they are, and gives it the synonyms of `synonyms.languages` after each rebuild and
with `phil:search:synonyms`.

| Method | Contract |
|---|---|
| `replaceSynonyms(string $index, array<string, list<string>> $synonyms): void` | Replaces the synonyms of an index: each phrase and the others it also finds |

## Configuration

| Option | What it extends |
|---|---|
| `adapter` | `dbal`, `meilisearch` or `loupe` |
| `connection`, `meilisearch`, `loupe.directory` | Where each adapter keeps the indexes |
| `index_entities` | Whether every entity a Doctrine flush changes is indexed |
| `batch` | How many documents a rebuild writes at a time |
| `synonyms.directory`, `synonyms.store`, `synonyms.rejected_forms` | Where the synonyms come from |
| `synonyms.languages` | The languages an engine that keeps synonyms is given |
| `kit_subdirectory` | Where the recipes of the kit are |

## Templates and controller

The template `@PhilSearch/components/SearchFilters.html.twig` is overridden as any bundle template, in
`templates/bundles/PhilSearchBundle/components/`. The component reads its recipes by name, so a kit with the same
names and props — the Tailwind kit `phil/ux-kit-shadcn` — draws it too. The controller dispatches nothing and reads
only its targets, `form`, `status` and `submit`, and its value `results`.

## Services

`Search` — `index()`, `remove()`, `forget()`, `reindex()`, `search()`, `publishSynonyms()` — is what the application
calls for resources that are not Doctrine entities, and to search. See [Searching](searching.md). `SearchRequest`
reads a results page's query string. See [Filters](filters.md).
