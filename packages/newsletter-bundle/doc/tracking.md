# Tracking

## Clicks

When a campaign counts clicks, each `http` or `https` link of its body is replaced by a signed link of the bundle,
which redirects to the address the campaign holds for it — never to an address the link carries, so the bundle is no
open redirect. The links of the layout, to unsubscribe and to the preference center, are never tracked.

| `ClickTracking` | Counts |
|---|---|
| `aggregate` | A total per link of the campaign. The default: the link names no recipient. |
| `per_recipient` | The total, and each recipient's clicks; dispatches `LinkClicked`, which the trigger `newsletter.link_clicked` of automations reads. An explicit choice of the campaign. |
| `off` | Nothing: the links are left as they are. |

```php
$engagement->clicks($campaignId);              // [link index => clicks]
$engagement->recipientClicks($subscriberId);   // per recipient, when chosen
```

## Machines

Mail providers' scanners and link previews open links before people do. They are left out of the counts without
reading an IP address or a user agent:

- a `HEAD` or `OPTIONS` request;
- a request whose `Sec-Purpose`, `Purpose`, `X-Purpose` or `X-Moz` header says it is a prefetch or a preview;
- a click within `clicks.minimum_seconds`, ten by default, of the message being sent.

The redirect happens all the same, so that a scanner sees a working link.

## What is never tracked

No message carries a tracking pixel, and nothing records the opening of a message: opening is not a signal one can
trust, and it is the reader's business. No trigger of automations reacts to it.

Every message, a confirmation included, carries Symfony Mailer's `TrackingHeader` (`X-Track: opens=false;
clicks=false`), which wins over the mail provider's default and over `framework.mailer.tracking`: a provider that
follows opens or clicks by default adds no pixel and rewrites no link, and the clicks of a campaign that counts them go
through `ClickTracker` alone. A transport that knows no such setting, plain SMTP among others, sends the header without
effect.
