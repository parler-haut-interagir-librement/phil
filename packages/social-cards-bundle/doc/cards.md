# Cards

## The life of a card

1. A page without an image names its card: `SocialCards::request()` hashes the renderer, the template and the content
   into a fingerprint, and keeps what the card is drawn from — the template's name, the title, the subtitle and the
   locale — as `<directory>/<first two digits>/<fingerprint>.json`.
2. The first time, the card is queued: with Messenger, a `GenerateSocialCard` message carries the fingerprint alone,
   and `GenerateSocialCardHandler` draws the card in a worker. Route the message to an asynchronous transport:

   ```yaml
   framework:
       messenger:
           routing:
               Phil\SocialCardsBundle\Infrastructure\Messenger\GenerateSocialCard: async
   ```

3. `/social-cards/{fingerprint}.png` serves the kept card, or draws it from its request when no worker drew it yet. A
   fingerprint no page named is a `404`.

The image changes only with its fingerprint, so its answer is `public` and `immutable`, kept `cache_max_age` seconds,
with the fingerprint as its `ETag` and a `304` to a request that holds it. A new title, template or GD gives a new
address, which no cache holds yet.

## Templates

Templates are declared in `cards.templates` by name, with the keys of `CardTemplate::fromArray()`; see the
[library's documentation of cards](../../social-cards/doc/cards.md). `CardTemplates` reads a template's fonts and
images the first time it is used and keeps it for the life of the process: a worker reads them once. A page picks a
template with `card_template`.

A card whose template the configuration no longer declares is a `404` until a page names it again under a template
that exists.

## The store

The cards and their requests are files under `cards.directory`, written under a temporary name and renamed into
place. A deployment on several servers points the directory at shared storage, or replaces the `CardStore` and
`CardRequestStore` aliases. Removing the directory removes nothing a page cannot draw again: the next view of each
page names its card anew.

## Cache tags

With phil/http-cache-tags-bundle, each served card is tagged `social-card` and `social-card:<fingerprint>`: purging
`social-card` empties a CDN of every card.
