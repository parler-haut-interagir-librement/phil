# Moderation and expiry

## Moderation before or after publication

A kind's `moderation` is `Moderation::BeforePublication` or `Moderation::AfterPublication`.

- **Before publication**: `submit()` sends a listing to `pending_review`, and nobody else sees it until a moderator
  calls `approve()`, which publishes it, or `reject()`, which returns it to its author with a `ModerationReason`. A
  published listing its author edits goes back to `pending_review`, with its expiry kept.
- **After publication**: `submit()` publishes a listing at once. Members report what should not be there.

A moderator can `remove()` a pending, published, hidden or expired listing for good, with its reason. The reasons are
keys — `spam`, `off_topic`, `unsafe` — that the application translates and lists.

## Reports

`report($policy, $reporter, $at)` counts a member's report on a visible listing; the author cannot report their own,
and a listing that is not visible cannot be reported. A member's second report changes nothing. The report that
brings the distinct reporters to the kind's `reportsToHide` turns the listing `hidden`, under every kind of
moderation: it leaves the view until a moderator restores it with `approve()`, which clears the reports and keeps its
expiry while it lies ahead, or removes it with `remove()`.

The reports themselves — their reason, their text, the queue a moderator reads — are the application's, or a
moderation bundle's: the listing counts the reporters it needs to decide when to hide.

## Expiry

A listing is published for the kind's `lifetimeDays` from its publication; past `expiresAt`, `isVisibleAt()` is
false. `at($at)` turns a published listing past its expiry into an expired one, for a scheduler that keeps statuses
current; an expired listing keeps everything else.

## Reminders

`needsReminderAt($policy, $at)` is true when the author should be reminded to extend: the listing is visible, it may
still be extended, `$at` is within the kind's `reminderDays` of its expiry, and no reminder was sent since its last
extension. The application sends the reminder — a notification carrying the listing's identifier — then saves
`markReminded($at)`. A kind with `reminderDays: 0` never reminds. In this sketch, `$store` is the application's store
of listings and `RemindToExtend` its message:

```php
foreach ($store->publishedExpiringBefore($now->modify('+7 days')) as $listing) {
    if ($listing->needsReminderAt($policies->get($listing->kind), $now)) {
        $bus->dispatch(new RemindToExtend($listing->id->value));
        $store->save($listing->markReminded($now));
    }
}
```

## Extensions

`extend($policy, $at)` adds the kind's `extensionDays` to a published or expired listing, counted from its expiry
or from `$at`, whichever is later: an early extension loses no day, a late one starts from today. An expired listing
is published again. Each extension counts against the kind's `maxExtensions`; past it, `extend()` throws
`ForbiddenTransition`, and the author writes a new listing. The reminder is due again before the new expiry.
