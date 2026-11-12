# phil/indexing-consent

**The reach of a public record, as the person it is about decides it: listed, kept out of search engines, or
unlisted.**

phil/indexing-consent is a small contract between a public record and what reads it beyond its own page. A record —
a profile, a listing in a directory, a post signed with a name — implements `HasIndexingConsent` and answers with an
`Indexing`: `Listed`, everywhere; `NotIndexed`, on the site and in its listings but out of search engines, the sitemap
and the structured data; `Unlisted`, reachable only by its address. Each reader asks `allows()` with what it is: the
sitemap leaves out what it may not list, the structured data says nothing of it, the directory hides it from its
listings, and the page tells robots `noindex, noarchive`.

It exists because a person may no longer want a record about them to be found by their name — privacy laws such as
Québec's Law 25 give them the right to have it de-indexed — without asking the site to remove it. The choice lives
on the record; every reader asks the same question and gets the same answer, so that withdrawing a record from
search engines does not depend on each reader remembering to.

It is a plain PHP library with no dependency. It stores nothing and renders nothing: the application keeps the choice
on its record, and the readers — a sitemap, the structured data of a page, a directory — ask it. A page carries the
choice of the record it shows on its request, under `IndexingPolicy::REQUEST_ATTRIBUTE`, so that its other readers
get the same answer; phil/structured-data-bundle then prints no JSON-LD, and phil/directory-bundle keeps its listings
to what their authors chose.

## Status

| Part | State |
|---|---|
| `Indexing`, `Reader` | Implemented and tested |
| `HasIndexingConsent` | Contract |
| `IndexingPolicy` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Reference](doc/reference.md) | The values, what each reader is answered, the robots directives, the strictest of two choices. |
| [Extension points](doc/extension-points.md) | The contract a record implements, and how a reader asks it. |
| [Testing](doc/testing.md) | How the library is tested. |

## Quick start

```php
final class Profile implements HasIndexingConsent
{
    public function __construct(private Indexing $indexing = Indexing::Listed)
    {
    }

    public function indexing(): Indexing
    {
        return $this->indexing;
    }
}

$profile = new Profile(Indexing::NotIndexed);

IndexingPolicy::allows($profile, Reader::Sitemap);      // false
IndexingPolicy::allows($profile, Reader::SiteListings); // true
$profile->indexing()->robots();                         // 'noindex, noarchive'

foreach (IndexingPolicy::filter($profiles, Reader::Sitemap) as $listed) {
    // add $listed to the sitemap
}
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
