# phil/structured-data

**The schema.org graph of a page as values: nodes from every part of a site, merged by `@id`, checked, and printed
as one JSON-LD script element.**

phil/structured-data models the structured data of a page as a graph. A `Node` is a schema.org thing — a site, an
organisation, a page, an event, a book, a package — with its types, its `@id` and its properties; it never changes.
Factories build the common types with named, typed arguments, `Schema` builds the types a site gives most and refuses
one that lacks what its type needs, and any other type takes free properties. A `Graph` gathers the nodes the parts of a
site give: two nodes with one `@id` become one, a nested node with an `@id` rises to the top and leaves a reference, and
the graph may carry the language of its texts. `JsonLd` writes it as one `<script type="application/ld+json">` element
that no value can close. The `Validator` reports what each node needs and lacks, as errors, and what it should have, as
warnings. `JsonLdReader` reads the JSON-LD of a page back.

It is for any PHP site that wants search engines and other readers to understand its pages: a site whose
organisation, breadcrumb and content come from different packages, a catalogue of software packages, a shop, a
calendar. In a Symfony application, phil/structured-data-bundle gathers the nodes of each request and prints them
with one Twig function.

It is a plain PHP library with no dependency beyond the DOM, JSON and libxml extensions of PHP. `NodeCollector` is
the port where the parts of an application pour their nodes; `TypeRules` add the rules of other types to the
validator.

## Status

| Part | State |
|---|---|
| `Node`, `Reference`, `LanguageString`, `Id`, `LanguageTag` | Implemented and tested |
| `Graph`: merging by `@id`, nested nodes lifted, language | Implemented and tested |
| Factories of 19 common types, and the enums of their values | Implemented and tested |
| `Schema`: checked factories of the 12 types a site gives most | Implemented and tested |
| `JsonLd`: the JSON-LD and its script element, with a nonce | Implemented and tested |
| `Validator`, `Rules`, `TypeRules`, `Report` | Implemented and tested |
| `JsonLdReader`: the JSON-LD of a page read back | Implemented, tested and fuzzed |

## Documentation

| Document | What it covers |
|---|---|
| [Reference](doc/reference.md) | Nodes, values, identifiers, languages, the graph and the factories. |
| [JSON-LD](doc/json-ld.md) | The document written, its script element, and the reader. |
| [Validation](doc/validation.md) | The rules of each type, the checks of values, the report. |
| [Extension points](doc/extension-points.md) | The port, the rules and the types an application adds. |
| [Testing](doc/testing.md) | How the library is tested, fuzzed and measured. |

## Quick start

```php
use Phil\StructuredData\{Graph, Id, JsonLd};
use Phil\StructuredData\Type\{Event, Organization, Place, WebSite};

$base = 'https://example.com/';
$organization = Organization::of('Le Gnome', $base, Id::of($base, 'organization'), logo: $base.'logo.png');

$graph = Graph::of(
    WebSite::of('Le Gnome', $base, Id::of($base, 'website'), inLanguage: 'fr-CA', publisher: $organization->reference()),
    $organization,
    Event::of('Tournoi de printemps', '2027-04-10T10:00:00-04:00', Place::of('Salle Saint-Denis'), organizer: $organization->reference()),
);

echo JsonLd::script($graph);
```

## Requirements

PHP 8.5 or later, with the DOM, JSON and libxml extensions; `phil/url` 0.1.3, which requires the intl, mbstring and URI
extensions.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
