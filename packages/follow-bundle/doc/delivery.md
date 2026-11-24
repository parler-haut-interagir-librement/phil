# Delivery

## Publishing an activity

The application publishes what happens on a subject:

```php
use Phil\FollowBundle\Application\Activities;

$activities->publish(
    new SubjectReference('thread', $thread->id),
    'reply.created',          // the kind of event, a key
    $author->id,              // the actor, who never hears of their own activity
    $mentionedIds,            // up to 200 members, who hear of it at the mentions level
    'reply:'.$reply->id,      // the application's identifier; a UUID v7 otherwise
);
```

The activity is kept in `phil_follow_activities`, then sent by a `FanOutActivity` message carrying its identifier only.
Published again under the same identifier — a retried request, a second listener —, it is kept once and sent to each
follower once.

The bundle sends nothing to a mentioned member who does not follow the subject: the application notifies its mentions
itself, with its own notification type.

## The pages

The handler of `FanOutActivity` reads the activity again from its identifier — one purged in the meantime is sent to
no one —, and the chain of its subject and parents. It then serves the followers a page of `batch_size` members at a
time:

1. two queries read the page's members, in byte order after the last member of the page before, and every follow they
   hold on the chain — one when the subject has no parents —;
2. the nearest follow of each member decides, at its level, whether the activity reaches them;
3. `FollowAccess` drops the members who may no longer see the subject;
4. the delivery is claimed for each member: one `INSERT` that skips the members already claimed, then one read of the
   rows that carry this attempt's token;
5. the members claimed get the notification through `Notifications::send()` of phil/notifications-bundle, one call
   for the page, which splits it into one `SendNotification` message per recipient on the bus.

With a bus, a full page ends with the `FanOutActivity` message of the next one, after its last member, so that every
message does a bounded amount of work; without a bus, the pages follow each other at once. A page costs seven queries
whatever its size.

## No delivery twice

A claim is kept per activity and member. A message retried after a failure, a page read twice, or two workers on the
same message send to each member once: the members claimed by an earlier attempt are left out. When the call to the
notifications fails, the claims of its members are given back and the exception goes up, for Messenger to try the
message again. Each notification also carries the key of duplicates `follow:<activity>`, which the window of duplicates
of phil/notifications-bundle reads when it is turned on.

## One notification on every channel until seen

With `email.once_until_seen`, a member who got a `follow.activity` about a subject — every channel of the type, email
included — gets the next activities of that subject as `follow.activity_quiet`, kept to the centre, until they see the
subject, or until `email.resend_after` seconds have passed. The application says when a member sees a subject:

```php
$follows->seen($member, new SubjectReference('thread', $thread->id));
```

Both types collapse: the centre shows one notification per subject and type, with the number of activities it stands
for.

## The digest

The digest is phil/notifications-bundle's: a member who chose a daily or weekly digest gets the email of
`follow.activity`, which allows it, in their digest instead of at once, collapsed by subject. The bundle needs no
schedule of its own for it.

## The data of a notification

| Field | Value |
|---|---|
| `type` | `follow.activity` or `follow.activity_quiet` |
| `subject` | The subject of the activity |
| `actor` | The actor, or none |
| `data` | `event`, the kind of event, and `activity`, its identifier |
| `dedupeKey` | `follow:<activity>` |

The translations of the bundle's types receive `%subject%`, `%actor%`, `%count%`, `%event%` and `%activity%`.

## The purge

`PurgeFollowRecords`, which the schedule `phil_follow` dispatches every day at `schedule.at`, deletes a thousand rows at a
time, a hundred times at most a run: the activities older than `retention.activity_days`, the claims of their deliveries,
and the marks of notifications older than `email.resend_after`. With a lock factory, the schedule runs on one worker
however many consume it; with a cache pool, it keeps its last run, so that a worker that restarts neither repeats nor
skips it.

## Workers

Every service of the bundle holds no state between messages or requests, as a long-running worker needs.
