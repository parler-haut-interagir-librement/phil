# Subscriptions

## Lists and subscribers

A `MailingList` belongs to a site and has a key, used in the subscription form's address, a name, whether it asks for
a double opt-in, and its sender profile. A `Subscriber` is a person on one site: their address, the identifier the
application knows them by when it gives one — the external id —, and where they stand. Their place on each list is a
`Membership`.

| `SubscriberStatus` | Means |
|---|---|
| `pending` | Waits for the confirmation of a double opt-in |
| `active` | Receives what their lists send |
| `paused` | Receives nothing until the pause ends, then receives again without asking |
| `unsubscribed` | Left every list |
| `bounced` | The address no longer receives mail: never mailed again |
| `complained` | Reported a message as spam: never mailed again |

```php
use Phil\NewsletterBundle\Application\Subscriptions;

$outcome = $subscriptions->subscribe($list, 'ada@example.com', externalId: $user->getId());
$subscriptions->confirm($subscriberId, $list->id);
$subscriptions->join($subscriberId, $otherList);
$subscriptions->leave($subscriberId, $list->id, UnsubscribeReason::TooFrequent);
$subscriptions->pause($subscriberId, new DateTimeImmutable('+4 weeks'));
$subscriptions->resume($subscriberId);
```

`subscribe()` validates the address with `phil/email-address` — its syntax, its top-level domain and disposable
domains — and throws `SubscriptionRefused` with the problems and a suggestion for a typo. It never asks the DNS: the
request that subscribes does not wait on a resolver. An address that bounced or complained is subscribed to nothing and
sent nothing. A list with a double opt-in keeps the subscription pending and sends the confirmation through Messenger;
its signed link is valid seven days. Each change is saved, then announced by an `AudienceChanged` event.

## The limits of the form

The public subscription form is limited by default: each post may send a confirmation to an address whose owner never
asked for it, and spends the quota and the reputation of the sender. `subscription.rate_limit` counts, with Symfony's
Rate Limiter, in the pool of `rate_limiter_storage`, under `lock.factory` when the application defines one:

| Count | Default | Refused |
|---|---|---|
| Per client address, or IPv6 /64 network | 10 an hour, sliding window | `429`, `Too many attempts: please try again later.` |
| Per address typed, on each site, subscribed or not | 5 an hour, sliding window | `429`, the same page |
| The confirmation of a double opt-in, per subscriber | one every 5 minutes, token bucket | No confirmation is sent; the page thanks as it always does |

The address typed is trimmed and lowercased, so that its spellings share their count, and it is counted whether it is
subscribed or not: the answers tell nobody who is on a list. A refusal by the client's count spends nothing of the
address's. The keys of the counts are keyed hashes of the secret: the store holds no address, IP address or email
address. A subscription refused by the delay keeps its place, pending, and announces nothing; `subscribe()` answers
`SubscribeOutcome::ConfirmationDelayed`. A person who subscribes again after the delay gets a new confirmation.

```yaml
phil_newsletter:
    subscription:
        rate_limit:
            per_ip: { limit: 20, interval: '30 minutes' }
            per_address: { limit: 3, interval: '1 day' }
            resend_delay: '15 minutes'
```

`rate_limit: false` turns the three off, for an application that limits the form before the bundle, such as at its
reverse proxy. The client address is read by `Request::getClientIp()`: behind a proxy, set `framework.trusted_proxies`,
or every client counts as the proxy. Point `rate_limiter_storage` at a pool every web server shares, such as Redis: a
pool of each server's own counts each server apart. Intervals and the delay take the format of the Rate Limiter
component, such as `15 minutes`; one that is not a positive duration is refused when the container is built.

## The domain check in the worker

With `check_mail_domain: true`, the default, the worker that handles `SendConfirmation` asks the DNS whether the
address's domain receives mail before it sends the confirmation:

| What the DNS says | What happens |
|---|---|
| MX records, or an A or AAAA record | The confirmation is sent. |
| No MX, A nor AAAA record, or a null MX (RFC 7505) | No confirmation; the subscription to the list becomes `refused`, and a notice is logged. |
| The lookup fails or times out | The confirmation is sent: a failure says nothing of the domain. |

The notice names the subscriber and the list by their identifiers and the answer of the DNS (`no_record` or
`null_mx`), never the address, as every message and log line of the bundle does. A refused subscription is not
announced. Subscribing again starts a new confirmation, and a new check: a domain whose mail servers were missing for a
while is accepted once they answer. An address at an IP literal is not checked.

The message itself carries the identifiers of the subscriber and the list only; what the worker needs is read from the
store when it handles it.

## The pages

| Page | What it does |
|---|---|
| Subscription | A form with the address, a CSRF token and a hidden field robots fill in, under [the limits of the form](#the-limits-of-the-form). It gives the same answer whatever became of the address, so that nobody learns from it who is subscribed. |
| Confirmation | A button that confirms; following the link alone confirms nothing, since mail scanners follow links. |
| Unsubscription | A choice between the list of the message and every list, and an optional reason. |
| Preference center | The site's lists to tick, a pause of one, four or twelve weeks, coming back, leaving everything. |

The pages are Twig templates under `@PhilNewsletter/pages/`, extending `@PhilNewsletter/pages/layout.html.twig`;
override that layout in `templates/bundles/PhilNewsletterBundle/pages/layout.html.twig` to wear the site's. Their
texts are in the translation domain `phil_newsletter`, in English and French. A link that is not the newsletter's, or
expired, gets a `404`. The pages are never cached, never indexed and send no referrer.

## One-click unsubscription

Every message of a campaign carries `List-Unsubscribe` and `List-Unsubscribe-Post: List-Unsubscribe=One-Click`, as RFC
8058 asks: a mail client's unsubscribe button sends a `POST` with `List-Unsubscribe=One-Click` to the signed link,
which removes the subscriber from the list of the message and answers `200` with an empty body. The link does not
expire. A subscriber left with no list is unsubscribed.

## Reasons to leave

A subscriber who leaves may say why: too many messages, not relevant, never signed up, another reason. The reason is
counted per site, list and day, with nothing that names who gave it.

```php
$engagement->unsubscribeReasons($site, new DateTimeImmutable('-30 days'));   // ['too_frequent' => 12, ...]
```

## Sites

A standalone site has one site, `default`, or the one `site` names. An application that serves several replaces the
`CurrentSite` service with one that reads the request's site; lists, subscribers and campaigns are then kept apart by
site, and a template without a site is shared by all of them. The same address on two sites is two subscribers.
