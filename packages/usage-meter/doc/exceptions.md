# Exceptions

Every exception implements `Phil\UsageMeter\Exception\UsageMeterException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `QuotaExceeded` | `RuntimeException` | `consume()` would take the subject past its limit; nothing was counted |
| `UnknownMeter` | `InvalidArgumentException` | a call names a meter that `Meters` does not hold |
| `InvalidUsage` | `InvalidArgumentException` | a meter name, a limit, a period, a subject, an amount, a key or a table name cannot be used, or the database platform is not supported |

`QuotaExceeded` carries the `Usage` at the moment of the refusal — used, limit, start, `resetsAt` — and the amount
requested, so that the application can say how much is left and when more comes back. Its message names the meter,
never the subject:

```text
The quota of "exports" is reached: 3 used of 3, 1 more requested; the window changes at 2026-04-01T00:00:00+00:00.
```

The other messages name what is wrong:

```text
No meter is named "imports".
A subject is named by 1 to 255 bytes.
An amount counted is one or more; got 0.
An idempotency key is 1 to 255 bytes.
A limit is zero or more; got -1.
"Exports" is not a meter name: up to 64 lowercase letters, digits, "_", "." or "-", starting with a letter or a digit.
The meter "exports" is declared twice.
"Mars/Olympus" is not a time zone.
A billing period lasts 1 to 120 months; got 0.
A rolling window lasts one second or more and is cut into 1 to 1440 buckets that divide it exactly; got 100 seconds in 7 buckets.
DbalUsageStore runs on PostgreSQL, MySQL, MariaDB and SQLite; not on Doctrine\DBAL\Platforms\SQLServerPlatform.
```

The database store lets the connection's own exceptions through after rolling its transaction back.
