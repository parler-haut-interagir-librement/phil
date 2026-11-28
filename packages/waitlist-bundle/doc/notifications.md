# Notifications

With phil/notifications-bundle installed and enabled, the bundle tells each participant what happened to their entry:
a place offered, an offer lapsed, and, when named, an entry joined or an offer accepted. Without it, nothing is sent,
and the events remain for the application's own listeners.

## Declaring the types

The notifications bundle sends only the types it knows. Declare them, with their channels and their templates:

```yaml
# config/packages/phil_notifications.yaml
phil_notifications:
    types:
        waitlist.offered:
            channels: [site, email]
            importance: high
        waitlist.lapsed:
            channels: [site]
```

The type of each event comes from the `notifications` options of `phil_waitlist`; a type left null, or not declared to
the notifications bundle, sends nothing:

```yaml
# config/packages/phil_waitlist.yaml
phil_waitlist:
    notifications:
        offered: waitlist.offered
        lapsed: waitlist.lapsed
        joined: waitlist.joined    # declare it too
        accepted: ~
```

## What a notification carries

| Field | Value |
|---|---|
| Recipient | The entry's participant |
| Subject | `waitlist_entry` and the entry's identifier |
| Data | `waitlist`: the list's identifier |
| Duplicate key | The type and the entry's identifier: one notification per offer |

What it says is written when it is shown, from the type's templates and the subject the application resolves. A
`SubjectResolver` for `waitlist_entry` reads the entry — and through it the list and its subject — to give the label and
the link, such as the session's title and the page that accepts the offer:

```php
use Phil\NotificationsBundle\Application\Contract\SubjectResolver;
use Phil\NotificationsBundle\Application\ResolvedSubject;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Phil\WaitlistBundle\Application\Waitlists;
use Phil\WaitlistBundle\Integration\Notifications\WaitlistNotifier;

final readonly class WaitlistEntrySubjects implements SubjectResolver
{
    public function __construct(private Waitlists $waitlists, private Sessions $sessions) {}

    public function resolve(SubjectReference $subject, ?string $locale): ?ResolvedSubject
    {
        if (WaitlistNotifier::SUBJECT !== $subject->type) {
            return null;
        }

        $entry = $this->waitlists->entry($subject->id);
        $session = $this->sessions->get($entry->waitlist()->subjectId());

        return new ResolvedSubject($session->title, '/waitlist/'.$entry->id);
    }
}
```

The participant identifier must be one the notifications bundle's `RecipientDirectory` knows, to reach them by email
or SMS; the notification centre needs none.
