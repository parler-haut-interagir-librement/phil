# Reminders

## The schedule

```php
use Phil\AccessLinks\ReminderSchedule;

$schedule = new ReminderSchedule(['P3D', 'P7D']);   // three days, then seven days after the invitation
```

The delays are ISO 8601 durations counted from the invitation's creation, in increasing order, ten at the most. A
reminder that would fall at the closure or after is not sent. Without a schedule, `new ReminderSchedule()`, no
reminder falls due.

When an invitation is created, its first reminder is set; each reminder sent sets the next; an answer or a revocation
stops them.

## Sending by hand

```php
foreach ($links->dueReminders(100) as $invitationId) {
    $links->remind($invitationId, $sender);
}
```

`remind()` claims the reminder first, in one conditional write that holds only while the invitation has no answer,
is not revoked or closed, and its reminder is still due with the count read: of two workers, one wins. Then it issues
a fresh link and hands the `IssuedLink` to the sender. A sender that fails after the claim loses that reminder rather
than sending it twice; the next one falls due on schedule.

## Through Messenger

```php
use Phil\AccessLinks\Messenger\ReminderDispatcher;

$dispatcher->dispatchDue(100);   // one message per due reminder; returns how many
```

`ReminderDispatcher` sends one `SendInvitationReminder` per due invitation. The message carries the invitation's
identifier and nothing else: no recipient, no address, no link, which the handler issues fresh when it runs. Route it
to an asynchronous transport:

```yaml
framework:
    messenger:
        routing:
            Phil\AccessLinks\Messenger\SendInvitationReminder: async
```

`SendInvitationReminderHandler` calls `remind()`. A message handled twice, after the recipient answered, or for an
invitation deleted since, sends nothing. Run the dispatcher from Symfony Scheduler or a cron job, every hour say.

## The sender

```php
use Phil\AccessLinks\Contract\ReminderSender;
use Phil\AccessLinks\IssuedLink;

final readonly class MailReminderSender implements ReminderSender
{
    public function sendReminder(IssuedLink $link): void
    {
        $member = $this->members->get($link->invitation->recipient);
        $this->mailer->send(new ReminderEmail($member->email, $link->url->toString()));
    }
}
```

The sender finds how to reach the recipient from their stable identifier, and sends the link. It never puts the link
in a message of a queue: send it at once, in the handler.
