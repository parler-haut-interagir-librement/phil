# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the lists elsewhere — a table, a remote service | Implement `Application\Contract\SynonymStore` — see [Stores](stores.md) |
| Read or write another format | Build `SynonymSet` and `SynonymList` from it; their factories check every limit |
| Check a list before saving it | Call `SynonymValidator` and show its `Violation`s |
| Search with the synonyms | Give a store to `phil/search-bundle`, `synonyms.directory` or `synonyms.store` |
