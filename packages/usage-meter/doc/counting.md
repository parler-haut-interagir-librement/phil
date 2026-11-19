# Counting

`UsageMeter` is built with the meters, a store, a PSR-20 clock, and optionally a limit provider and an anchor
provider:

```php
use Phil\UsageMeter\UsageMeter;

$meter = new UsageMeter($meters, $store, $clock, $limits, $anchors);
```

A **subject** is the application's opaque reference of 1 to 255 bytes — an account identifier, a tenant, a project.
The library never interprets it and never writes it in a message.

## Before an action: `consume()`

```php
use Phil\UsageMeter\Exception\QuotaExceeded;

try {
    $meter->consume($accountId, 'exports', 1, $exportRequestId);
} catch (QuotaExceeded $exception) {
    // nothing was counted
    return $this->render('upgrade.html.twig', ['usage' => $exception->usage]);
}

$this->exporter->export($document);
```

`consume()` counts the amount, 1 by default, before the action it stands for, unless it would take the subject past
its limit; then it throws `QuotaExceeded` and counts nothing. The check and the count are one atomic step of the store:
two requests at the same moment can never both pass the last unit. On a rolling window, what the earlier buckets hold
is taken off the limit first.

An action that fails after `consume()` gives its amount back with `refund()` and the same key, below; consume as late
as possible all the same, right before the work that the quota protects.

## After the fact: `record()`

```php
$meter->record($accountId, 'ai.tokens', $response->usage->totalTokens, $response->id);
```

`record()` counts what was already used, whatever the quota: the window may end up over its limit, and `consume()`
then refuses until the window frees enough. It suits use that is only known once the work is done, such as the
tokens a model spent.

## Idempotency keys

Both take an optional key of 1 to 255 bytes: the request identifier, a message identifier, a job identifier. An
increment with a key already counted for the same subject and meter counts nothing and answers the usage as it is,
marked `replayed` — a retried request, a message delivered twice, a double click. A replayed key succeeds even when the
quota is full: it was counted the first time. A refused increment leaves its key free, so the same request may be
tried again once the window allows it. A key is kept as long as the counter it went to.

## When the action fails: `refund()`

```php
$meter->consume($accountId, 'exports', 1, $exportRequestId);

try {
    $this->exporter->export($document);
} catch (ExportFailed $exception) {
    $meter->refund($accountId, 'exports', $exportRequestId);

    throw $exception;
}
```

`refund()` gives back what `consume()` or `record()` counted under a key, so that an action counted and then failed
costs nothing. Without an amount it gives back everything the key counted; with one, the amount is what the key's
refunds come to **in all**, not an addition: refunding 2 then 2 again gives back 2, refunding 2 then 5 gives back 5.
It never gives back more than the key counted, so a refund retried — a message delivered twice, a handler run again —
is harmless.

The amount returns to the counter the key went to, even when the window has moved on since: a refund never frees room
in a period it was not counted in. `refund()` answers the usage of the current window, marked `replayed` when nothing
was given back: an unknown key, a key already forgotten by the purge, or one refunded that far already. Only an
increment with a key can be refunded.

## Reading: `usage()`, `allows()`, `limit()`

```php
$usage = $meter->usage($accountId, 'exports');

$usage->used;        // 2
$usage->limit;       // Limit::of(3)
$usage->remaining(); // 1, or null for no limit
$usage->allows(2);   // false
$usage->isExceeded();// true only when more is used than the limit
$usage->start;       // the first instant counted
$usage->resetsAt;    // when the window next changes

$meter->allows($accountId, 'exports');      // to show or hide a button
$meter->limit($accountId, 'exports');       // the subject's limit, whatever is used
```

`allows()` is a check, not a reservation: between it and `consume()` another request may take the last unit, and
`consume()` decides.

## Amounts

An amount is a whole number from 1 — a refund's too, when it names one —; zero or a negative number throws
`InvalidUsage`. Meter in the smallest unit the quota speaks of: a token, a byte, a request, a project.
