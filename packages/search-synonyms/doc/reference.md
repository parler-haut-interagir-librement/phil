# Reference

Every public type of the package, with its role.

| Type | Kind | Role |
|---|---|---|
| `Domain\ValueObject\SynonymSet` | value | A rule, equivalent or one-way; `normalize()`, how a phrase is compared; `MAX_PHRASES`, `MAX_LENGTH`, `MAX_WORDS` |
| `Domain\ValueObject\SynonymList` | value | The rules of a language; `language()`, the primary subtag of a tag; `MAX_SETS` |
| `Domain\ValueObject\Violation` | value | What the validator noticed; `OVERLAP`, `CHAIN` |
| `Domain\Exception\InvalidSynonyms` | exception | A rule, a list, a file or a document refused; `fileLine` |
| `Application\SynonymValidator` | service | What a list holds that is likely a mistake |
| `Application\Contract\SynonymStore` | interface | Where the lists are kept |
| `Infrastructure\Format\SolrSynonymsReader`, `SolrSynonymsWriter` | services | The Solr synonyms format; `MAX_BYTES`, `MAX_LINE` |
| `Infrastructure\Format\JsonSynonyms` | service | The JSON document; `MAX_BYTES` |
| `Infrastructure\Store\FileSynonymStore` | store | A file per language; `EXTENSION` |
| `Infrastructure\Store\InMemorySynonymStore` | store | Lists in memory |
