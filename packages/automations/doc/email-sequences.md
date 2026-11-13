# Email sequences

An email sequence is an automation like any other: a trigger of the newsletter's package, actions that send a message
or change a subscription, delays between them, filters and branches on the subscriber's identifiers.

```json
{
    "id": "onboarding",
    "version": 1,
    "name": "Onboarding",
    "trigger": {"type": "newsletter.confirmed", "options": {"list": "members"}},
    "steps": [
        {"id": "welcome", "type": "action", "action": "newsletter.send", "options": {"template": "welcome"}, "retries": 3},
        {"id": "wait-3-days", "type": "delay", "seconds": 259200},
        {"id": "tips", "type": "action", "action": "newsletter.send", "options": {"template": "tips"}, "retries": 3},
        {"id": "wait-a-week", "type": "delay", "seconds": 604800},
        {"id": "events", "type": "action", "action": "newsletter.subscribe", "options": {"list": "events"}}
    ]
}
```

The newsletter's package registers those triggers and actions when this library is installed; another package may
offer its own.

## What a sequence never does

A sequence never starts, waits or branches on the opening of an email. Opening is not a signal one can trust — mail
clients load images ahead of the reader, and privacy protections hide it — and it needs a tracking pixel the
newsletter's package does not send. A sequence reacts to what the person does on the site: a subscription confirmed, a
form answered, an order paid, a link clicked when per-recipient click tracking was chosen.

A sequence stops sending to a person who unsubscribed or paused: the sending action checks the subscription at each
step, so a run that waits three weeks never mails someone who left in between.
