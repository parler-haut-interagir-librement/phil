# Testing

| Area | What is covered |
|---|---|
| `Interval`, `Booking`, `BookingRequest`, `Decision` | Their values, every refusal, spans that touch |
| Rules | Each rule at its bounds, on the resource's clock |
| Availability | Weekly hours, spans across midnight, days closed, changes of time, the bound of days |
| `Bookable` and `LoadProfile` | Capacity, buffers, replaced bookings, reasons in order, slots by step, quantity, rules and bounds |
| `ReminderSchedule` | Offsets, moments, the reminder due, reminders overtaken, bounds |

Infection holds the library at 100 % of mutants killed.

```sh
vendor/bin/phpunit packages/reservation/tests
```

## Benchmarks

The phpbench subjects under `tests/Benchmark` measure checking a request and listing the slots of a week among
hundreds of bookings. From the monorepo root:

```sh
composer bench -- packages/reservation/tests/Benchmark
```
