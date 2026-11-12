# Previews and checks

## Reading the tags of a page

`TagReader::read($html, $url, $charset)` reads a page nobody here wrote. It cuts the page at `$maxBytes` — 512 KiB by
default — and at the end of its head, parses what is left with the HTML5 parser of PHP, and reads:

- the `<title>`, on one line;
- the first `<link>` whose `rel` contains `canonical`, resolved against the page's address;
- the `content` of each `<meta>` by its `property`, else its `name`, in lower case.

At most 300 tags and 300 links are read, at most 10 values are kept for one key, a key longer than 100 characters is
dropped, and each value is put on one line and cut at 2,048 characters. A tag the cut leaves unfinished is dropped, as
the HTML5 parser drops it. The character set the server declared is used when PHP knows it; otherwise the parser reads
the page's own `<meta charset>`. A page that is not HTML gives empty tags; the reader never throws.

## Link previews

`LinkPreview::fromTags()` makes the card of a link from the tags:

| Field | First of |
|---|---|
| `url` | `og:url`, the canonical link — each on the host of the page read only —, the page's address |
| `title` | `og:title`, `twitter:title`, `<title>`, the host |
| `description` | `og:description`, `twitter:description`, `description` |
| `image` | `og:image`, `og:image:url`, `og:image:secure_url`, `twitter:image`, `twitter:image:src` |
| `imageAlt` | `og:image:alt`, `twitter:image:alt` |
| `siteName` | `og:site_name`, the host |
| `type`, `locale` | `og:type`, `og:locale` |

Every address is resolved against the page's address, then read by phil/url, and kept only when it is an absolute
HTTP or HTTPS address without credentials on a public host name: `javascript:` and `data:` never come out, nor an IP
address, `localhost`, a name under `.localhost` or a single label such as `intranet`. An address comes out in the
canonical form of phil/url: host in lowercase and in ASCII, default port dropped.

The address of the preview is the address of the page read, the one the fetcher ended on. A page names its own
address in `og:url` and in its canonical link, but nothing stops a page from naming the address of another site
there, and a preview shown under that address would pass for that site. `og:url`, then the canonical link, replaces
the page's address only when it is on the same host and port; `www.example.com` is not `example.com`. When the
page's address is not one phil/url accepts, it is kept as given and neither replaces it.

`toArray()` and `fromArray()` keep a preview in a cache; `fromArray()` reads its addresses with phil/url again and
drops a preview whose address is not accepted.

## Finding the links of a text

`LinkFinder::find($text, $max = 10)` finds the links a member wrote or pasted in a plain text, in the order they
appear, each once; `LinkFinder::first($text)` gives the first one, the link a message previews.

- A link starts with `http://` or `https://`, not inside a word, and runs to the next blank, quote, angle bracket,
  brace, backtick or control character.
- The marks that end a sentence after it are not part of it: `.`, `,`, `;`, `:`, `!`, `?`, `'`, `»`, `”`, `’`, `›`
  and `…`; nor is a closing parenthesis or bracket it did not open: `(see https://example.org/wiki/A_(b))` gives
  `https://example.org/wiki/A_(b)`.
- The first 64 KiB of the text are read, as UTF-8, an invalid byte replaced; a link longer than 2,048 bytes is left
  out; the scan stops at `$max` links. Trimming a link costs the same for each mark, whatever its length.

It checks the shape of a link only: whoever fetches one checks the whole address and what its host resolves to.

## Checking a page

`TagCheck::check($tags)` returns what the sites that show links would trip on:

| Severity | Finding |
|---|---|
| Error | A missing `og:title`, `og:type`, `og:url` or `og:image`; an `og:url` or `og:image` that is not absolute; a `twitter:card` that is not `summary`, `summary_large_image`, `app` or `player` |
| Warning | A missing `og:description` or `twitter:card`; a title above 90 characters or a description above 200; an image without its replacement text, without its size, or smaller than 200×200; an `og:url` other than the canonical link; a `fediverse:creator` that is not an account |

`TagCheck::hasErrors()` says whether one finding is an error.
