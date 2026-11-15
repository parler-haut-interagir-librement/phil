# Integrations

## Automations

With `phil/automations` installed, the bundle registers, with the tags `phil.automations.trigger` and
`phil.automations.action`:

| Trigger | Starts on | Options |
|---|---|---|
| `newsletter.subscribed` | A subscription, confirmed or not | `list`, a list key |
| `newsletter.confirmed` | A subscription confirmed, or a list joined | `list` |
| `newsletter.unsubscribed` | A list left, or every list | `list` |
| `newsletter.paused`, `newsletter.resumed` | A pause, its end | — |
| `newsletter.link_clicked` | A click on a campaign that counts clicks per recipient | `campaign` |

The run is about the subscriber; its payload holds the subscriber's id, the list's key, the site and the external id.

| Action | Does | Undone by |
|---|---|---|
| `newsletter.send` | Queues a template, by its key, to the subscriber; once per step of a run | — a message cannot be unsent |
| `newsletter.subscribe` | Adds the subscriber, already confirmed, to a list of their site | Removing them, when the step added them |
| `newsletter.unsubscribe` | Removes the subscriber from a list | Adding them back, when the step removed them |

A message queued by an automation is checked when it is sent: a subscriber who left or paused in between gets nothing.
See the email sequences of `phil/automations`.

## Privacy requests

With `phil/privacy-bundle` installed, `NewsletterPrivacy` answers its access and erasure requests for the module
`newsletter`, the data subject being the external id the application gave its subscribers, on every site. The export
lists each subscription — address, state, dates, lists —, the messages sent and the clicks counted per recipient; the
erasure deletes them all, and leaves a suppressed address as its keyed hash.

## Audience webhooks

With `phil/standard-webhooks-bundle` installed and `webhooks` configured, each `AudienceChanged` is sent, through
Messenger, to the receivers that asked for its type, signed by that bundle's keys:

```json
{"type": "newsletter.subscriber.confirmed", "timestamp": "2026-11-13T10:00:00+00:00",
 "data": {"subscriber": "0192…", "external_id": "user-42", "site": "default", "list": "news"}}
```

The event types are `subscribed`, `confirmed`, `unsubscribed`, `paused`, `resumed`, `bounced`, `complained` and
`erased`. The body never holds the address. Every attempt of one event to one receiver has the same `webhook-id`; a
failure worth retrying is left to Messenger's retries, another is logged and dropped.

## Segments

The recipients of a campaign come from the `SegmentResolver` service. The bundle's own, `ListRecipients`, sends to
every subscriber of the list who may receive, and refuses a campaign that names a segment. An audience package
replaces it with a resolver that knows segments.
