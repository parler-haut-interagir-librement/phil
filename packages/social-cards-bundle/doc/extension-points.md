# Extension points

## `ShareMetadataSource`

`Phil\SocialCardsBundle\Application\Contract\ShareMetadataSource` — the values of a page from a model of the
application, autoconfigured with the tag `phil_social_cards.source`.

| Method | Contract |
|---|---|
| `values(): array` | The keys of `ShareMetadata::KEYS`, `card_template` and `card`, or an empty array; a null value is a missing one |

## `CardRequestStore`

`Phil\SocialCardsBundle\Application\Contract\CardRequestStore` — what each card is drawn from, by fingerprint; the
alias points at `DirectoryCardRequestStore`.

| Method | Contract |
|---|---|
| `save(string $fingerprint, CardRequest $request): bool` | Keeps the request; whether it was not kept yet |
| `find(string $fingerprint): ?CardRequest` | The request, or null |

## `CardQueue`

`Phil\SocialCardsBundle\Application\Contract\CardQueue` — has a card drawn out of the request; `MessengerCardQueue`
with Messenger, none otherwise.

| Method | Contract |
|---|---|
| `queue(string $fingerprint): void` | Queues the fingerprint, and nothing else |

## `PageFetcher`

`Phil\SocialCardsBundle\Application\Contract\PageFetcher` — fetches the head of a page of another site; the alias
points at `GuardedPageFetcher`. A replacement keeps its guards.

| Method | Contract |
|---|---|
| `fetch(string $url): ?FetchedPage` | The HTML up to its bound, the address after the redirects and the declared character set, or null |

## The guarded client

The service `phil_social_cards.safe_http_client` is a `Phil\SafeHttp\SafeHttpClient` with the limits of `preview`.
An application that pins hosts, or reads through a proxy, decorates `http_client`: the client wraps whatever
`http_client` is in its own guard.

## Services the bundle relies on

| Service | Used for |
|---|---|
| `Phil\SocialCards\Contract\CardStore` | The cards; an alias of `DirectoryCardStore` |
| `request_stack`, `router` | The page's address and locale, the card's address |
| `messenger.default_bus` | The queue, when it exists |
| `http_client` | The guarded client's transport, else one of its own |
| `limiter.<name>` | The rate limit of the pasted links, when `preview.limiter` names it |
| `cache.app` | The previews, when it exists |
| `http_kernel`, `router.request_context` | The command, for a path of the application |
| `Phil\MediaBundle\Infrastructure\Twig\MediaExtension` | A medium as an image, when it exists |
| `Phil\HttpCacheTagsBundle\Application\ResponseTags` | The tags of the cards, when it exists |
