# Link previews

The bundle shows the card of a link to another site in two places: a link a template writes, through the
`PhilSocialCards:LinkPreview` component, and a link a member pastes in a form, through the `link-preview` Stimulus
controller and its endpoint. Both read the page through the same guarded fetcher and the same cache.

## The guarded fetcher

`GuardedPageFetcher` fetches the head of a page of another site through phil/safe-http. The addresses come from members
and editors, so the client guards against server-side request forgery:

- an address is an absolute HTTP or HTTPS address of a host name, as phil/url reads it: no IP address in any of its
  spellings, no user name nor password, 2,048 characters at most;
- the client is wrapped in `NoPrivateNetworkHttpClient`, which refuses a host that is or resolves to a loopback,
  private, link-local or reserved address — a cloud metadata endpoint, a service of the internal network — at the first
  request and at each redirect;
- the redirects are followed one by one, each checked as the first address, up to `preview.max_redirects`; the silence
  is bounded by `preview.timeout` and the whole fetch, redirects included, by `preview.max_duration`;
- only a `200` answer of `text/html` or `application/xhtml+xml` is read, in chunks, until the end of its head or
  `preview.max_bytes`, and the transfer is then cancelled.

Whatever fails gives nothing, never an exception. The client is the service `phil_social_cards.safe_http_client`, made
from `http_client` when the application has one, and forgets the addresses it resolved between requests.

## The previews

`LinkPreviews::preview($url)` reads the page's tags where it was served from after its redirects, makes its
`LinkPreview` and keeps it in `cache.app` for `preview.cache_ttl` seconds; a page that cannot be fetched is kept as
nothing, so that a dead link is not fetched on every view. Without `cache.app`, every call fetches.

`LinkPreviews::firstIn($text)` is the preview of a pasted link: the first link of the text, as `LinkFinder` of
phil/social-cards finds it, that the client fetches. One link is fetched at most, whatever the text holds.

## The component

```twig
<twig:PhilSocialCards:LinkPreview url="https://example.org/post" />
<twig:PhilSocialCards:LinkPreview url="{{ link }}" :image="false" class="compact" />
<twig:PhilSocialCards:LinkPreview :snapshot="message.linkPreview" />
```

It renders a link with the page's image, site, title and description, as `phil-link-preview` and its `__image`,
`__body`, `__site`, `__title` and `__description` parts, or a plain link, `phil-link-preview--plain`, when the page
gives nothing; an address that cannot be fetched, a `javascript:` or `data:` one among them, gives no link at all. The image is loaded lazily and without a referrer; `:image="false"` leaves it out, so that a reader's
browser fetches nothing from the other site. The links are `rel="noopener nofollow"`.

`:snapshot` takes a preview kept with a message, the array of `LinkPreview::toArray()`: nothing is fetched, and the card
stays as it was when the message was written, whatever the page becomes. It wins over `url`; an array that is not a
preview gives the plain link of `url`, or nothing.

A `url` is fetched while the page renders; a page that shows many links to sites not cached yet renders as slowly as
the slowest of them, bounded by `preview.max_duration` each. A message's preview is better read when the message is
written, and kept with it.

## Pasted links

The endpoint and the Stimulus controller show, below a text a member writes, the preview of its first link, and let
the author remove it.

```yaml
# config/routes/phil_social_cards.yaml
phil_social_cards_link_preview:
    resource: '@PhilSocialCardsBundle/config/link_preview_routes.php'

# config/packages/framework.yaml
framework:
    rate_limiter:
        link_preview: { policy: sliding_window, limit: 30, interval: '10 minutes' }

# config/packages/phil_social_cards.yaml
phil_social_cards:
    preview:
        limiter: limiter.link_preview
```

`GET /social-cards/link-preview?url=…`, `LinkPreviewController`, answers:

| Status | When | Body |
|---|---|---|
| `200` | The page gives a preview | The JSON of `LinkPreview::toArray()` |
| `204` | The page gives none, or cannot be fetched | Empty |
| `400` | `url` is missing, or is not an address the client fetches | `{"error": "…"}` |
| `429` | The visitor's rate limiter is spent | `{"error": "…"}`, with `Retry-After` |

Each answer is `private`, kept five minutes by the browser, and `nosniff`. Each call may fetch a page of another site:
import the route behind the firewall of the members who write, and give `preview.limiter` a rate limiter, keyed by the
client's address. An address refused does not count against the limit.

```twig
<div data-controller="phil--social-cards-bundle--link-preview"
     data-phil--social-cards-bundle--link-preview-endpoint-value="{{ path('phil_social_cards_link_preview') }}">
    {{ form_widget(form.message, {attr: {
        'data-phil--social-cards-bundle--link-preview-target': 'source',
        'data-action': 'input->phil--social-cards-bundle--link-preview#scan paste->phil--social-cards-bundle--link-preview#scan',
    }}) }}
    {{ form_widget(form.link, {attr: {'data-phil--social-cards-bundle--link-preview-target': 'field'}}) }}
    <div data-phil--social-cards-bundle--link-preview-target="preview" hidden></div>
</div>
```

The controller scans the text `delay` milliseconds — 400 by default — after the author stops typing, finds its first
link as `LinkFinder` does, asks the endpoint for it and builds the card from text nodes, never from HTML, with a
button, `phil-link-preview__remove`, labelled by its `remove-label` value. The link shown is written in the `field`
target, a hidden field of the form; a link removed empties it, and stays removed while it is in the text. An answer
that comes after the text changed is dropped. It dispatches `shown` with the preview and `removed` with the address.

On submission, the application reads the field, takes the preview again — from the cache — and keeps its array with
the message:

```php
$link = $form->get('link')->getData();
$message->linkPreview = is_string($link) && '' !== $link ? $previews->preview($link)?->toArray() : null;
```

The address of the field comes from the browser: `preview()` checks it as any other.
