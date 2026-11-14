# Channels

A type names each recipient's channels; the recipient may turn any of them off. The dispatcher then:

| Channel | Delivery |
|---|---|
| `site` | Kept in the recipient's centre, collapsed into a similar unread one when the type says so, and shown as a toast with the new unread count |
| `email` | Symfony Notifier's email channel, at the address of the directory; left to the digest when the recipient chose one, the type allows it and the centre keeps the notification |
| `web_push` | The `WebPushSender`: the bundle's, on minishlink/web-push, with `web_push` enabled, or the application's own |
| `sms`, `push`, `chat`, `desktop`… | Symfony Notifier's channel, at the address of the directory; a transport after a slash, `sms/twilio` |

## Broadcast channels

`broadcast` names the channels a notification is posted to once, whoever its recipients are: a Slack or Discord channel
through `chat/slack` or `chat/discord`, with the bridges of Symfony Notifier. Its text is written in the default
locale: the title, a blank line and the body, since most bridges post a message's text alone — Discord's webhook
would drop the body. Slack's bridge also renders the title and the body as two blocks, with that text as its
fallback.

## Quiet hours

During a recipient's quiet hours, the toast and every channel but the centre and email are held back: the notification
waits in the centre. Quiet hours run from `start` included to `end` excluded, in the recipient's time zone, past
midnight when `start` comes after `end`.

## Opt-outs

`type:channel` turns a channel off for one type, `*:channel` for every type. The centre itself can be turned off.

## Web Push

With `web_push` enabled, the bundle keeps each recipient's browsers and sends to them; an application that names its
own `web_push_sender` keeps the subscriptions, the endpoints and the button, and sends as it wishes.

### Turning it on in a browser

`<twig:PhilNotifications:WebPush />` shows a toggle button, `aria-pressed`, to the current recipient, in a browser
that can register a service worker and receive push messages; elsewhere, and without JavaScript, nothing shows. Its
Stimulus controller registers the bundle's service worker, asks the permission when the member presses the button —
never before —, subscribes the browser with the application's VAPID public key and sends the subscription to
`push_routes.php`, with the centre's CSRF token. Pressed again, it forgets the subscription on both sides. A status
region says what happened, a permission refused included.

The endpoints answer only the current recipient, checked as the centre's changes are:

| Endpoint | Answer |
|---|---|
| `POST /push/subscribe` | `201`, the subscription kept; `422` when it is no subscription or its push service is not allowed; `413` above 4096 bytes |
| `POST /push/unsubscribe` | `204`, the endpoint forgotten; `404` when the recipient did not subscribe it |

### What is kept

A subscription is the browser's endpoint and two keys, read by `PushSubscription`. The endpoint is an HTTPS address of
a public host — an IP address, `localhost`, credentials or port 0 are refused — on one of `push_services`, the push
services of Chrome, Edge, Firefox and Safari by default: the site posts to no other address a member could name. Each
recipient keeps their ten latest browsers; an endpoint subscribed again moves to the recipient who did, as when someone
else signs in on the same browser. `PushSubscriptions::forgetRecipient()` forgets them all when a member leaves.

### Sending

`MinishlinkWebPushSender` sends a notification to each browser of its recipient with minishlink/web-push: encrypted
for that browser (RFC 8291), signed with the VAPID keys (RFC 8292), posted with Symfony HttpClient — private networks
refused, redirections not followed, ten seconds to answer. A push service that answers `404` or `410` no longer knows
the subscription, which is forgotten; any other answer is logged with the push service's host, never the endpoint,
which is a secret of its browser. Quiet hours and opt-outs (`*:web_push`) apply as to any channel.

`WebPushMessage::of()` gives what is sent for a notification of a type:

| Property | What it holds |
|---|---|
| `payload` | The JSON the service worker shows: `id`, `type`, `title`, `body`, `url`, `count`; at most 3993 bytes, what one encrypted record of 4096 bytes holds, the body shortened with an ellipsis first, then the title, then the URL left out |
| `urgency` | The `Urgency` header: `high` for an `urgent` or `high` type, `normal` for `medium`, `low` for `low` |
| `topic` | The `Topic` header of a type that collapses — 32 URL-safe characters from a hash of its group —, so that a push service replaces an older message of the same group it still holds; `null` otherwise |
| `ttl` | The `TTL` header: `web_push.ttl`, a day by default, four weeks at most |

### The service worker

`assets/worker/push-worker.js` shows each message as a notification tagged with its notification's identifier, so
that a later message of a collapsed notification replaces it, and opens its page when clicked — a page of this site
only. AssetMapper serves it under `phil-notifications-worker/`, its scope the folder it is served from: it handles push
messages and leaves the site's pages alone. An application with a service worker of its own sets `web_push.worker` to
that worker's URL and loads the bundle's script into it with `importScripts()`, at the URL AssetMapper gives
`phil-notifications-worker/push-worker.js`; its two listeners, `push` and `notificationclick`, sit beside the
worker's own.

### A sender of the application's

```php
final readonly class BrowserPush implements WebPushSender
{
    public function __construct(private NotificationTypes $types, private PushSubscriptions $subscriptions) {}

    public function send(string $recipient, RenderedNotification $notification): void
    {
        $message = WebPushMessage::of($notification, $this->types->get($notification->notification->type));

        foreach ($this->subscriptions->of($recipient) as $subscription) {
            // Encrypt $message->payload for $subscription and post it with its urgency, topic and time to live.
        }
    }
}
```

## Addresses

Channels that need an address ask the `RecipientDirectory` when the notification is sent, never before: an
`EmailRecipientInterface` for email, an `SmsRecipientInterface` for SMS. A recipient the directory does not know is
skipped and logged.
