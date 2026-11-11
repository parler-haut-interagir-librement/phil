# Forms

`OpeningHoursType`, to and from `OpeningHours`:

- a field a day, `monday` to `sunday`, named in the request's language by ICU, where the day's ranges are typed —
  `09:00-12:00, 13:00-17:00`, nothing when closed;
- `specials`, special hours one period a line, `2026-12-25 # Christmas`;
- `timezone`, a choice of time zones.

A field that cannot be read is invalid on its own, with a message that shows how to type it. Hours with no open day
and no special hours are `null`.

```php
$builder->add('hours', OpeningHoursType::class, ['timezone' => 'Europe/Paris', 'with_timezone' => false]);
```

| Option | Default | Means |
|---|---|---|
| `timezone` | `null`: `default_timezone` | The time zone of new hours |
| `with_timezone` | `true` | Whether people choose the time zone |
| `locale` | `null`: the request's | The language the days are named in |

Labels are in the `phil_opening_hours` translation domain, in English and French; the days need none.
