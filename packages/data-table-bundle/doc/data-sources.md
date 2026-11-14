# Data sources

```php
interface DataSource
{
    public function fetch(TableQuery $query): ResultPage;

    /** @param list<string> $ids */
    public function find(array $ids): array;
}
```

`TableQuery` holds the criteria (`TextCriterion`, `ChoiceCriterion`, `DateRangeCriterion`, `BooleanCriterion`), the sorts,
the page and its size. Every field in it comes from the table's declaration. `ResultPage` holds the rows and the count of
all matching rows.

`find()` returns the rows of these identifiers that the source holds. An identifier outside the source is left out, which
keeps an action or an edit inside the table it came from.

## Doctrine

```php
new DoctrineDataSource(
    $queryBuilder,
    ['id' => 'b.id', 'title' => 'b.title', 'author' => 'a.name'],
    identifier: 'id',
    fetchJoinCollection: false,
    toIdentifier: static fn (string $id): Uuid => Uuid::fromString($id),
);
```

`$fields` is the allow-list. Only its keys reach the query, as the DQL expressions they map to. A field that is not in it
throws `UnknownField`. Every value is a bound parameter. The wildcards of a text filter are escaped. The builder given is
never changed: each call works on a copy, so scope the rows on it.

`DataTable::source()` builds the adapter for each opening of the table. Set `fetchJoinCollection` when the query
fetch-joins a collection. A page is read through the ORM's `OffsetPaginator`, the window of the page given apart from the
query, and its count is the `WindowPage`'s total; `find()` reads its rows in one window as wide as the identifiers asked,
and nothing at all for none.

## In memory

`new InMemoryDataSource($rows, 'id')` filters, sorts and pages rows already loaded. Fields are array keys or property
paths.

## Paging and its bounds

Tables page by offset, bounded. Three limits apply to every query:

- the page size is one of those the table offers, 500 at most;
- the page stops at the table's `maxOffset`, 10 000 rows by default. Beyond it the table shows its last reachable page and
  says that more rows match, so people narrow the filters;
- a page past the last row asks once more for the last page.

The sorts always end on the identifier, so rows that tie keep their place between two pages.

An offset costs more the further it goes, and the count runs on every render. A table over a very large set lowers
`maxOffset`, indexes its sortable fields, or implements `DataSource` itself. An implementation may use keyset paging
internally, translating `TableQuery::offset()` from its own anchors, or return an estimated count.
