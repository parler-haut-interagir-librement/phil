# phil/audience-segments

**Pick who receives a message by readable JSON rules on tickets, purchases, support and tags, with frozen recipient
lists and global exclusions, no profiling.**

phil/audience-segments is a plain PHP library. A segment is a named rule of [phil/rules](../rules/README.md) on the
facts of an audience member — holds a ticket of an event, took part in a date, was chosen by an organiser, bought a SKU,
supports a tier, carries an internal tag —, written in a JSON query language that an API and a visual builder share.

- **Dynamic segments**, computed from the facts as they are now, read by bounded pages, or recalculated on a schedule
  with a `MemberEntered` or `MemberLeft` event for each change.
- **Frozen lists**: the exact members at a date, in opaque identifiers, with the fingerprint of the rule, kept with a
  message as the proof of who it was sent to.
- **Global exclusions**: unsubscribed members and refusals are removed from every segment before its rule is read.
- **Facts through ports** that the ticketing, events, shop, support and people modules implement: the library reads
  no table of its own and needs no external customer data platform.
- **No profiling**: no guessed trait, no behaviour score, no filter on whether a message was opened or clicked. A
  segment refuses condition types whose names say they do.

Events and messages carry identifiers only, never an address or a name.

## Status

| Part | State |
|---|---|
| `Segment`, `SegmentJson`: the query language, read, written, fingerprinted | Implemented and tested |
| The audience condition types and their ports | Implemented and tested |
| `Segments`: one member with the reason, bounded pages, frozen lists | Implemented and tested |
| `Recalculation`: stored members, entry and exit events, date of the last run | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, wiring the ports. |
| [Segments](doc/segments.md) | The query language, dynamic segments and frozen lists, exclusions, recalculation, what is refused. |
| [Extension points](doc/extension-points.md) | The ports, with an implemented example, and what is stable. |
| [Testing](doc/testing.md) | The tests and the benchmark. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/audience-segments
```

```php
use Phil\AudienceSegments\AudienceConditions;
use Phil\AudienceSegments\SegmentJson;
use Phil\AudienceSegments\Segments;

$json = new SegmentJson(AudienceConditions::types(participation: $participation, tags: $tags));
$segment = $json->read('fair-2027-buyers', '{"version": 1, "name": "Spring fair buyers", "condition": {"any": [
    {"type": "ticket.holder", "operands": {"event": "spring-fair-2027"}},
    {"type": "person.tagged", "operands": {"tags": ["press"]}}
]}}');

$segments = new Segments($subscribers, $exclusions, $clock);
$page = $segments->page($segment, after: null, limit: 100); // members now, without the excluded
$list = $segments->freeze($segment);                        // the recipients of one message, kept with it
```

## Requirements

PHP 8.5 or later, phil/rules, and the PSR clock and event dispatcher interfaces.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
