# Publication batches

A `PublicationBatch` groups items published together: a launch in two languages, the parts of a series, a page and
the posts that announce it. It has an identifier, a title, its items in their order — at least one, each once —, and a
moment in UTC, or none when it is applied by hand.

```php
$batch = new PublicationBatch('launch', 'Launch', ['launch-fr', 'launch-en'], new DateTimeImmutable('2026-12-01 14:00Z'));
$manual = new PublicationBatch('press', 'Press kit', ['kit', 'release']);
```

## One entry, one moment

An item of a batch takes the batch's moment: views show the batch as one entry carrying its items, the review counts it
as one publication, and the iCalendar export writes it as one event. An item of a manual batch has no moment until the
batch is applied. An item scheduled without a moment given takes its batch's.

## Applied whole, or not at all

`applyBatch($id, $now)` publishes every item of the batch at `$now`. When one of them cannot be published — it is still
a draft, or was withdrawn —, none is: `BatchFailed` names the batch and the item, carries the refused transition, and
the calendar stays as it was. An item of a batch is never published alone: `transition()` refuses it.

```php
try {
    $calendar = $calendar->applyBatch('launch', $now);
} catch (BatchFailed $failed) {
    $failed->item;          // 'launch-en'
    $failed->getPrevious(); // the InvalidTransition it refused
}
```

`dueBatches($now)` lists the batches whose moment has come and that still hold an item to publish, in the order of
their moments; `manualBatches()` lists those applied by hand that still do.

## With a scheduler

The calendar decides what a batch publishes and refuses a batch that cannot be published whole; publishing the
contents themselves is the application's work. The contract it runs with its scheduler is:

1. At the batch's moment, or when an editor applies a manual batch, call `applyBatch()` on the stored calendar.
2. In one database transaction, apply the publication of each content of the batch and store the items the new
   calendar holds.
3. If any of them fails, roll the transaction back: no content is published, and the stored calendar is unchanged.

Because the calendar is immutable, step 1 has no effect until step 2 stores its result: a failure at any step leaves
nothing applied in part.
