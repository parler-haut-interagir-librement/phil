# Review

`Review::issues($calendar, $from, $to)` lists the conflicts and gaps of a calendar from `$from`, included, to `$to`,
excluded. Each `Issue` holds its kind, the UTC moments it spans and the identifiers of the items it concerns.

| `IssueKind` | Reported when | `from` and `to` |
|---|---|---|
| `DuplicateContent` | The same content in the same language is planned more than once | The first and last moments |
| `NotReady` | An item is planned for a moment while still an idea or a draft | Its moment |
| `TooClose` | Two publications are closer than the minimum spacing | The two moments |
| `Gap` | A stretch longer than the maximum gap has no publication | The stretch |

Issues come in that order of kinds, each kind in the order of its moments. A batch counts as one publication at its
moment, its items each counted for repeated content and readiness; withdrawn items are left out; transitions planned
elsewhere are no publications.

## The rules

`ReviewRules` sets what the review looks for besides repeated content and unready items:

```php
new Review(new ReviewRules(minimumSpacing: 3_600, maximumGap: 172_800));
```

| Rule | Default | Effect |
|---|---|---|
| `minimumSpacing` | 0 | The seconds wanted at least between two publications; 0 ignores spacing |
| `maximumGap` | `null` | The seconds without publication beyond which a gap is reported, from the start of the range to the first publication, between publications, and from the last to the end; `null` reports none |

A negative spacing, or a gap below one second, throws `InvalidItem`.
