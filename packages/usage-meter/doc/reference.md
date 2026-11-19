# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\UsageMeter\UsageMeter` | `final readonly class` | The library in one object: `consume()`, `record()`, `usage()`, `allows()`, `limit()`, `purge()` |
| `Phil\UsageMeter\Usage` | `final readonly class` | A subject's use of a meter in the current window |
| `Phil\UsageMeter\Limit` | `final readonly class` | A whole number from zero, or no limit |
| `Phil\UsageMeter\Meter` | `final readonly class` | What is counted, its period and its default limit |
| `Phil\UsageMeter\Meters` | `final readonly class` | The meters of an application, by name |
| `Phil\UsageMeter\Counter` | `final readonly class` | One counter of a store: subject, meter, start, expiry |
| `Phil\UsageMeter\Increment` | enum | What a store did with an increment: recorded, refused, duplicate |
| `Phil\UsageMeter\UsageStore` | interface | Where the counters live |
| `Phil\UsageMeter\InMemoryUsageStore` | `final class` | The counters of one process |
| `Phil\UsageMeter\LimitProvider` | interface | A subject's limit of a meter, from the application |
| `Phil\UsageMeter\LimitChain` | `final readonly class` | Several limit providers, the first that answers winning |
| `Phil\UsageMeter\AnchorProvider` | interface | A subject's own anchor, for billing cycles |
| `Phil\UsageMeter\Period\PeriodPolicy` | interface | How a meter cuts time |
| `Phil\UsageMeter\Period\Window` | `final readonly class` | The stretch of time a quota covers, and the current counter |
| `Phil\UsageMeter\Period\CalendarPeriod` | `final readonly class` | Days, ISO weeks, months or years in a time zone |
| `Phil\UsageMeter\Period\CalendarUnit` | enum | Day, week, month or year |
| `Phil\UsageMeter\Period\BillingCycle` | `final readonly class` | Periods of months counted from an anchor |
| `Phil\UsageMeter\Period\RollingWindow` | `final readonly class` | The last so many seconds, in buckets |
| `Phil\UsageMeter\Dbal\DbalUsageStore` | `final readonly class` | The counters in two tables of a Doctrine DBAL connection |
| `Phil\UsageMeter\Dbal\UsageSchemaListener` | `final readonly class` | The tables added to the schema the ORM generates |
| `Phil\UsageMeter\Subscription\FeatureLimits` | `final readonly class` | Limits written `usage:<meter>:<limit>` in features |
| `Phil\UsageMeter\Exception\UsageMeterException` | interface | Every exception of the library |
| `Phil\UsageMeter\Exception\QuotaExceeded` | `final class` | An action refused before it passed the quota |
| `Phil\UsageMeter\Exception\UnknownMeter` | `final class` | A meter that was not declared |
| `Phil\UsageMeter\Exception\InvalidUsage` | `final class` | A value the library cannot work with |
