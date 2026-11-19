# iCalendar

`ICalendarWriter` writes the publications of a calendar between two moments as an iCalendar stream (RFC 5545), for a
calendar application to subscribe to.

```php
$writer = new ICalendarWriter('example.org');

return new Response($writer->write($calendar, $from, $to, $now, 'Blog'), 200, [
    'Content-Type' => 'text/calendar; charset=utf-8',
]);
```

Each item planned alone and each batch is one `VEVENT`:

| Property | Value |
|---|---|
| `UID` | `item-<id>@<domain>` or `batch-<id>@<domain>`, stable across exports |
| `DTSTAMP` | The moment given to `write()`, in UTC |
| `DTSTART` | The moment of the item or the batch, in UTC |
| `SUMMARY` | The item's title, or the batch's title and its number of items |
| `DESCRIPTION` | Each item: title, language, state and content reference |
| `CATEGORIES` | The item's state, or `batch` |
| `STATUS` | `CONFIRMED` when every item is scheduled or published, `TENTATIVE` before |

Texts are escaped — backslashes, semicolons, commas and line breaks —, lines end with CRLF and are folded at 75 octets,
never inside a UTF-8 character. Withdrawn items and transitions planned elsewhere are not exported. The domain, which
makes the identifiers unique, must be a host name; another value throws `InvalidItem`.

A planned publication happens once: no event repeats, so the writer needs no recurrence rule.
