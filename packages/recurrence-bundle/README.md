# phil/recurrence-bundle

**A form for repeating events, a column to store the rule, and the rule in words.**

phil/recurrence-bundle brings `phil/recurrence` into a Symfony application. `RecurrenceRuleType` is a form for the
rules people set: every so many days, weeks, months or years, on weekdays, on a day of the month or the nth weekday,
in some months, until a date or a number of times. The column type `phil_rrule` stores a rule with Doctrine. In Twig,
`rrule_text` writes a rule in words, such as *Tous les mois le deuxième mardi*, and `rrule_occurrences` lists its
dates, and `rrule_schema` gives it as schema.org data for JSON-LD. Labels and sentences come in English and French.

It is for an application where an editor or an organizer schedules repeating events through a form and visitors read
when they happen. The form holds the rules people actually set. A rule beyond its fields, such as one with set
positions, still reads in the library; the form shows what it can hold of it, and gives it back whole when it is
submitted untouched. Reach for `phil/recurrence` alone
outside Symfony, or when no form or storage is needed. `phil/calendar-bundle` builds a whole calendar on this bundle.

The form plugs into Symfony Form through a data transformer to and from a `RecurrenceRule`. A small Stimulus
controller shows the rows of the frequency and the end chosen, and disables the others so they are not sent. The
sentences are ICU messages in the `phil_recurrence` domain, with days and months named by ICU in the reader's
language.

## Status

| Part | State |
|---|---|
| `RecurrenceRuleType` and the Stimulus controller `recurrence` | Implemented and tested |
| `RuleDescription`: rules in words, ICU messages | Implemented and tested |
| The column type `phil_rrule` | Implemented and tested |
| Twig: the filter `rrule_text`, the functions `rrule_occurrences` and `rrule_schema` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and registering. |
| [Forms](doc/forms.md) | `RecurrenceRuleType`, what it holds, its Stimulus controller. |
| [Words, Twig and storage](doc/words-twig-storage.md) | `RuleDescription`, the Twig filter and functions, `phil_rrule`, schema.org. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```sh
composer require phil/recurrence-bundle
```

```php
#[ORM\Column(type: 'phil_rrule', nullable: true)]
private ?RecurrenceRule $rule = null;

$builder->add('rule', RecurrenceRuleType::class, ['required' => false]);
```

```twig
{{ event.rule|rrule_text }}
```

## Requirements

PHP 8.5 or later with `ext-intl`; `phil/recurrence` 0.1; Symfony 8.2's dependency injection, form and options
resolver, and the translation contracts — with a translator that reads ICU messages. Doctrine DBAL and Twig are
optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
