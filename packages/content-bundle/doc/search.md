# Search

With `phil/search-bundle` in the kernel, the published contents are fed to one of its indexes, `content` by default
(`phil_content.search_index`), through `Infrastructure\Search\ContentDocumentProvider`. The index follows what readers
see: each Doctrine flush that publishes a locale adds or replaces its document, and unpublishing or deleting removes it.

## The documents

One document per live localized row of the declared roots:

| Part | Holds |
|---|---|
| Identifier | the kind, the identifier of the root and the locale: `news-0192f3c4-…-fr_CA` |
| `title` | the excerpt title, else the SEO title |
| `body` | the passage of the published UJO (Universal JSON Output) in the row's own language |
| `body_<language>` | each passage another language tunes, as `body_en` for an English quotation in a French page, so the index reads each in its own language |
| Filter `kind` | the resource key |
| Filter `locale` | the locale of the row, as `fr_CA` |
| Filter `reference` | the content reference, to link to the content |

- **The passages** are those of `phil/ujo`'s `PlainText::passages()`: each block at the top of the document goes to the
  language of its `language` tune, in lower case, or to the row's language. Empty passages and fields are left out.
- **A row marked `noindex` has no document**: it is not found.
- **Rebuilding** (`phil:search:reindex content`) reads every root of every kind, in each declared locale, and indexes
  its live row.

## Searching

```php
use Phil\SearchBundle\Application\Search;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;

$result = $search->search('content', SearchQuery::of('opening', ['locale' => 'fr', 'kind' => 'news']));
// $result->ids: the identifiers of the documents found
```

The application turns a result into a link with the `reference` filter of the document, or by reading the identifier
back, and `content_url()` ([rendering.md](rendering.md#twig-functions)).
