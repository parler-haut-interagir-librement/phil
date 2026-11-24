# A home page

The bundle keeps no activity feed. What a member sees on their home page is composed by the application from two
sources it already has: the subjects the member follows, and their notifications.

## The subjects a member follows

`Follows::following()` gives the member's own follows a page at a time, by subject type then identifier, every level
included:

```php
$subjects = [];
$after = null;

do {
    $page = $follows->following($member, $after, 200);

    foreach ($page as $follow) {
        if ($follow->level()->follows()) {
            $subjects[$follow->subject->type][] = $follow->subject->id;
        }

        $after = $follow->subject->key();
    }
} while (200 === \count($page));
```

The application then reads its own records of those subjects — the latest threads of the forums, the next events of
the calendars, the new listings of the categories — with its own queries and its own order. A subject the member
ignores is left out; a child of a followed parent is the application's to read, as it knows the parents.

## The notifications

The centre of phil/notifications-bundle holds what reached the member: `follow.activity` and `follow.activity_quiet`,
one per subject, with the number of activities each stands for. Its Twig components show them on the home page as
anywhere else.

## Seeing a subject

When the member opens a subject, the application tells the bundle, so that the next activity goes out on every channel
again:

```php
$follows->seen($member, new SubjectReference('thread', $thread->id));
```

## What a home page never does

The bundle offers no suggestions drawn from who follows whom, ranks nothing by the number of followers, and builds no
profile of a member's interests: a home page composed this way shows what the member chose to follow, in the order the
application gives its own records.
