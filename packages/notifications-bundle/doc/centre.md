# Notification centre

## The components

| Component | Shows |
|---|---|
| `<twig:PhilNotifications:Bell limit="10" />` | A button with the unread count that opens the latest notifications; nothing for a visitor |
| `<twig:PhilNotifications:List limit="20" />` | The latest notifications on a page of their own |
| `<twig:PhilNotifications:Toasts />` | The region toasts appear in, once per layout: those the session kept, and those Mercure brings |
| `<twig:PhilNotifications:WebPush />` | With `web_push` enabled, the button that turns notifications on in this browser; see [Channels](channels.md#web-push) |

Each notification is listed with its title — a link to its subject when its resolver gives a URL —, its body, its time,
and its buttons: mark it read, archive it, and mark all read. Each button is a form posting to the centre's endpoints
with the CSRF token: it works without JavaScript and comes back to the page. The templates live under
`@PhilNotifications/` and can be overridden as any bundle's.

## The endpoints

Under the prefix the application chose, for the recipient of the current request only:

| Route | Method | Answer |
|---|---|---|
| `phil_notifications_unread`, `/unread` | `GET` | `{"unread": n}`, not cached |
| `phil_notifications_read`, `/{id}/read` | `POST` | Marks the notification read |
| `phil_notifications_read_all`, `/read-all` | `POST` | Marks every notification read |
| `phil_notifications_archive`, `/{id}/archive` | `POST` | Archives the notification |

A change answers `{"unread": n}` to a request that accepts JSON, and redirects a form with `303` to the page it came
from on this site, or to `/`. A visitor, a request without the token or sent from another site gets a `403`; a
notification of someone else, a `404`. The token is `phil_notifications`, stateless: in the form field `_token` or in
the header `X-Phil-Notifications-Token`, checked with the browser's `Sec-Fetch-Site`, `Origin` or `Referer`.

## Toasts

| `toasts.transport` | How a toast reaches the browser |
|---|---|
| `mercure` | Pushed to the recipient's private topic as `{"unread": n, "toast": {"id", "type", "title", "body", "url", "count"}}` |
| `flash` | Kept in the session's flash bag when the notification is for whoever makes the request, shown by the next page |
| `none` | No toast; the notification is in the centre |

The application authorizes each recipient to subscribe to their own topic only, `urn:phil:notifications:<id>`, for
instance with the authorization cookie of MercureBundle:

```twig
{% do mercure('urn:phil:notifications:' ~ app.user.id, {subscribe: ['urn:phil:notifications:' ~ app.user.id]}) %}
```

A hub that cannot be reached, or that refuses the update, is logged as a warning and does not fail the delivery: the
notification is in the centre, and the next page shows the count.

### Sending outside a request

With FrankenPHP's built-in hub — MercureBundle chooses it when the hub's `url` is empty —, an update is published
inside the request FrankenPHP serves, by `mercure_publish()`. That function does not exist in a console command nor in
a Messenger consumer, where notifications are usually delivered: there, every toast would be logged as not pushed. An
application that sends notifications outside a request names, in `toasts.hub`, a hub reached by its URL: FrankenPHP's
built-in hub also answers over HTTP to a publisher's token.

```yaml
# config/packages/mercure.yaml
mercure:
    hubs:
        default:
            url: ~                                   # FrankenPHP's built-in hub, for the pages
            jwt: { secret: '%env(MERCURE_JWT_SECRET)%', subscribe: '*' }
        publisher:
            url: '%env(MERCURE_URL)%'                # the same hub, reached over HTTP
            jwt: { secret: '%env(MERCURE_JWT_SECRET)%', publish: '*' }

# config/packages/phil_notifications.yaml
phil_notifications:
    toasts:
        hub: mercure.hub.publisher
```

## The Stimulus controller

`phil--notifications-bundle--notifications`, set by the bell, with its configuration in a value: the endpoints' URLs,
the CSRF token and its header, the subscription URL of the recipient's topic, the label of the close button. It opens
and closes the panel and keeps `aria-expanded` in step, sends the forms in the background and updates the count, and
subscribes to the topic to add toasts and update the count as notifications come. A toast's text is set as text, never
as markup, and its link is followed only to a path of the site or an `http(s)` address.

`phil--notifications-bundle--web-push`, set by the Web Push button, registers the service worker, subscribes the
browser and sends the subscription with the CSRF token; see [Channels](channels.md#web-push).

## Accessibility

The toasts region is a polite live region. The bell names its count in words for screen readers. The Web Push button
keeps one label and says its state with `aria-pressed`; its status region, polite, says whether notifications are on,
off, or blocked by the browser. Toasts slide in
unless the visitor asks for less motion: then they appear without moving and stay longer, ten seconds instead of six.
The stylesheet takes PHIL UX's tokens — colours, radius, shadow, focus ring, durations — when that stylesheet is
loaded, the page's own colours otherwise.

## Turbo

The controller connects again on each page Turbo renders. With `symfony/ux-turbo`, give the bell an `id` and
`data-turbo-permanent`, which the component passes on: Turbo then keeps the same element from one visit to the next,
and with it a single `EventSource` to the hub, instead of closing and opening one on every page.

```twig
<twig:PhilNotifications:Bell id="notifications-bell" data-turbo-permanent />
```

The centre's forms answer with a `303` redirect, which Turbo follows after a form submission, and the unread count
stays JSON for a request that also accepts Turbo Streams.
