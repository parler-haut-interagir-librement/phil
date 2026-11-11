# Extension points

What an application extends, overrides or listens to. The rules are `phil/recurrence`'s.

## Words

The sentences of `RuleDescription` and `rrule_text` are ICU messages of the `phil_recurrence` domain, and so are the
labels of the form: an application changes the words, or adds a language, by overriding the messages — see
[Words, Twig and storage](words-twig-storage.md).

## Forms

| Point | How |
|---|---|
| What the form offers | `RecurrenceRuleType` writes the common rules — frequency, interval, days, month day or weekday, months, end; a rule beyond them is kept by a field of the application's that holds the RFC 5545 text |
| More than the form does | A form type extension of the application's on `RecurrenceRuleType` |

The Stimulus controller shows the rows of the frequency and the end chosen: its targets are `frequency`, `end` and
`row`, each row naming in `data-show` the frequencies or ends it is for, so a form theme of the application's keeps
them. See [Forms](forms.md).

## Storage and Twig

| Point | How |
|---|---|
| Where a rule is kept | The `phil_rrule` column type, the RFC 5545 text |
| The occurrences of a page | `rrule_occurrences`, with its bounds |
| Structured data | `rrule_schema`, a schema.org `Schedule` |
