# phil/resource

**Point at any record of your application by its kind and identifier, such as `page:42`.**

phil/resource holds one value type, `ResourceReference`: a kind and an identifier, written `kind:identifier`, as in
`page:42` or `event:0192f3c4-…`. It is what a trash keeps, a usage index points at, a taxonomy tags or a schedule
moves, without knowing the resource's class. A reference against the rules throws `InvalidResource`.

It is for the bundles that point at resources, so that a reference one of them gives is one the others take.
`phil/trash-bundle`, `phil/references-bundle`, `phil/taxonomy-bundle`, `phil/scheduled-transitions-bundle` and
`phil/calendar-bundle` share it. An application that needs a typed link to one entity class is better served by a
plain Doctrine association.

It is a plain PHP library with no dependency. `of()` builds a reference from its parts, `fromString()` reads it
back, `toString()` writes it and `equals()` compares two of them.

## Status

| Part | State |
|---|---|
| `ResourceReference`, `InvalidResource` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Reference](doc/reference.md) | The type, its rules, reading it back from its string. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | How the library is tested. |

## Quick start

```php
$page = ResourceReference::of('page', '42');
$page->toString();                                  // 'page:42'
ResourceReference::fromString('event:urn:uuid:1');  // kind 'event', identifier 'urn:uuid:1'
```

## Requirements

PHP 8.5 or later; nothing else.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
