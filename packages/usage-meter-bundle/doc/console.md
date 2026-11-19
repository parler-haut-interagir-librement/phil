# Console

## phil:usage-meter:purge

```sh
bin/console phil:usage-meter:purge
```

Forgets the counters, and their idempotency keys, that no window counts any more: periods that ended, buckets that left
their rolling window. It prints how many rows went:

```text
42 counter(s) and key(s) forgotten.
```

Nothing breaks without it, but the store grows by one row per subject, meter and period, and one per bucket of a
rolling window. Once a day keeps it small. With Symfony Scheduler:

```php
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\RecurringMessage;

RecurringMessage::cron('17 3 * * *', new RunCommandMessage('phil:usage-meter:purge'));
```

Or from the system's cron:

```text
17 3 * * * /srv/app/bin/console phil:usage-meter:purge --no-interaction
```

The command reads the moment from the `clock` the bundle is configured with.
