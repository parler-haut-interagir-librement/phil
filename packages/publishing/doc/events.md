# The events

## Two events, one contract

| Event | Means |
|---|---|
| `Phil\Publishing\PagePublished` | The page went online, or went online again with new content: readers see it at its canonical address |
| `Phil\Publishing\PageRetired` | The page went offline — taken offline, or deleted —: readers no longer see it at that address |

Both implement `Phil\Publishing\Contract\PageEvent` and are immutable. A listener that treats both alike — a cache
purge, a ping — types its argument with the contract; a listener that adds or removes something — a sitemap entry —
listens to each class.

## The fields

| Field | Type | Holds | Checked |
|---|---|---|---|
| `canonicalUrl` | `Phil\Url\Url` | The address readers find the page at | By `Url` itself: an absolute `http` or `https` address, normalised, 2048 characters at most |
| `locale` | `string` | The language of the page, a BCP 47 tag: `fr`, `fr-CA`, `zh-Hant-TW`, `es-419` | Two or three letters, then subtags of one to eight letters or digits, separated by hyphens; 35 characters at most. `fr_CA`, a locale identifier of ICU, is refused |
| `indexable` | `bool` | Whether search engines may index the page. For a retired page, whether they could while it was online | — |
| `ownerId` | `string` | The identifier the owning package gives the page, as `news:42` | 1 to 255 bytes, without a control character, a leading or a trailing space |

A field that fails its check throws `Phil\Publishing\Exception\InvalidPageEvent`, an `InvalidArgumentException`,
whose message quotes at most 40 characters of the value.

The letter case of a language tag is kept as given: `fr-CA` and `fr-ca` name the same language, and an owner
dispatches one spelling.

## For the owner of a page

- **Dispatch once the change is stored.** After the transaction commits, never inside it: a listener acts on a page
  readers can already see, or no longer see, and a rolled-back publication dispatches nothing.
- **One event per page and language.** A page published in three languages is three `PagePublished`, each with the
  address of its language.
- **The same `ownerId` for every event of a page.** A listener keys what it keeps by it, with the language: a sitemap
  entry, a queued ping. Prefix it with the kind of page, so two kinds never collide.
- **A page with no address dispatches nothing.** An event needs an absolute address; a content readers cannot reach
  is not a page.
- **Publishing again dispatches `PagePublished` again.** A listener treats a second one for the same page as an
  update: the content, and perhaps the address or the flag, changed.

## For a listener

- The event is the whole message: it carries what a listener needs to act, never the page itself. A listener that
  needs more asks the owner by `ownerId`.
- A listener may run later, from a queue: the fields are scalars and a `Url`, which serialises.
- `indexable` false on a `PagePublished` is a page readers see and search engines must not: a sitemap leaves it out,
  a search engine ping skips it, a cache purges it all the same.
