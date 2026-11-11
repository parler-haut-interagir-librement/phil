# Forms

`RecurrenceRuleType`, to and from a `RecurrenceRule`:

| Field | Shows for | Becomes |
|---|---|---|
| `frequency` | always | `FREQ`; no frequency is no rule |
| `interval` | every frequency | `INTERVAL` |
| `weekdays` | daily, weekly | `BYDAY`, without places: *every weekday* is daily on Monday to Friday |
| `month_day` | monthly, yearly | `BYMONTHDAY`, from the end when negative |
| `month_weekday`, `weekday` | monthly, yearly | `BYDAY` with its place — the second Tuesday, the last Friday —, taking over the month day |
| `months` | yearly | `BYMONTH` |
| `end`, `count`, `until` | always; `count` and `until` for their end | `COUNT` or `UNTIL`, a day |

Days and months are named in the request's language by ICU; the labels are in the `phil_recurrence` domain. A rule
that cannot be — an interval of 0, an interval or a count above 9999, an end without its value — makes the form
invalid; the `interval` and `count` fields carry `min` 1 and `max` 9999. A rule beyond what the fields
hold — set positions, several month days, a weekday next to a month day (*Friday the 13th*), a week start, an end
time — shows what they hold of it: the form is for the rules people set, the library reads them all.

Such a rule is never rewritten by a form submitted without touching it. The transformer remembers the rule it showed
and what its fields meant; when the fields come back meaning the same rule, the form gives back the rule it was
given, whole. Once a field changes, the form gives the rule the fields say, and what they could not hold is gone —
an editor who changes the schedule of an imported rule sets it again. One transformer serves one form, as
`RecurrenceRuleType` builds it.

## The Stimulus controller

The form carries `phil--recurrence-bundle--recurrence`; each row says, in `data-show`, the frequencies or ends it is
for. When the page connects and on each change, the controller shows the rows of the frequency and the end chosen and
disables the fields of the others, so that they are not sent.
