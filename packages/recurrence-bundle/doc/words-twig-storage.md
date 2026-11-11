# Words, Twig and storage

## `RuleDescription`

```php
$description->describe(RecurrenceRule::parse('FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE;COUNT=10'), 'fr');
// "Toutes les 2 semaines le lundi et le mercredi, 10 fois"
```

The sentences are ICU messages of the `phil_recurrence` domain — plurals, ordinals, *le 1er*, *the 2nd* —, the days,
months and end dates written by ICU, in the language given or the translator's. An application changes the words by
overriding the messages. A formatter is built once for each language, pattern and time zone, then kept: a list of
events writes each of its rules without building a formatter for every name of a day or a month. Up to 64 formatters
are kept, then built again, so that languages and time zones taken from requests cannot grow the memory of a worker
that lives across requests.

An end time, `UNTIL=20270101T045959Z`, is a moment: `describe()` takes the time zone of the recurrence's first
occurrence as third argument, and writes the day the end falls on there — *until 31 December 2026* in Toronto. Without
it, the end is written in its own zone, UTC for a rule read from text. An end day is written as it is.

## Twig

| Name | Kind | Gives |
|---|---|---|
| `rrule_text(locale = null, timezone = null)` | filter | The rule in words; empty for `null`. `timezone` — a zone, its name, or the first occurrence itself — is where an end time is a day |
| `rrule_occurrences(start, rule, limit = 10, from = null)` | function | The occurrences from a first one, from a moment; the first alone without rule |

## `phil_rrule`

With DoctrineBundle, the column type `phil_rrule`: a `RecurrenceRule` in a `VARCHAR(255)`, as RFC 5545 writes it —
its end in UTC or as a day, so that it reads back the same. A value that is not a rule is refused on writing; a text
that is no rule, on reading.

```php
#[ORM\Column(type: 'phil_rrule', nullable: true)]
private ?RecurrenceRule $rule = null;
```

## schema.org

| Function | Gives |
|---|---|
| `rrule_schema(start, rule)` | The recurrence as a schema.org `Schedule`, or `null` |

The data composes into a JSON-LD document printed by the template:

```twig
<script type="application/ld+json">{{ data|json_encode(constant('JSON_UNESCAPED_SLASHES') b-or constant('JSON_UNESCAPED_UNICODE') b-or constant('JSON_HEX_TAG'))|raw }}</script>
```

`JSON_HEX_TAG` writes `<` and `>` as escapes, so no value can close the script element.
