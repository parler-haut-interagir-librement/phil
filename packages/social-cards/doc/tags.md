# Share tags

## The metadata of a page

`ShareMetadata` holds what a page says of itself when it is shared. Only the title and the address are required.

| Field | Key of `fromArray()` | Rule |
|---|---|---|
| `title` | `title` | On one line, cut at 300 characters; a title of blanks only is refused |
| `url` | `url` | An absolute HTTP or HTTPS address without credentials, of 2,048 characters at most |
| `description` | `description` | On one line, cut at 1,000 characters |
| `image` | `image` | A `ShareImage`, an address, or an array of `url`, `alt`, `width`, `height` and `type` |
| `type` | `type` | A `ShareType` or its value: `website` (the default), `article`, `profile`, `book`, `video.other` |
| `locale` | `locale` | A language, maybe a script and a region: `fr`, `fr-CA` and `fr_ca` are written `fr_CA` |
| `alternateLocales` | `alternate_locales` | 50 at most, written as `locale`, without duplicates nor the page's own locale |
| `siteName` | `site_name` | On one line, cut at 200 characters |
| `author` | `author` | On one line, cut at 200 characters; written for an article only |
| `publishedTime`, `modifiedTime` | `published_time`, `modified_time` | A date, or a string PHP reads; written for an article only |
| `twitterSite`, `twitterCreator` | `twitter_site`, `twitter_creator` | An account on X: 1 to 15 letters, digits or underscores, the `@` optional |
| `fediverseCreator` | `fediverse_creator` | An account on the fediverse, `@user@example.social`, the first `@` optional |

"On one line" means that whitespace, line separators and control characters become single spaces, that the invisible
formatting characters — the controls of the direction of the text, the zero-width spaces, the soft hyphen — are
removed, but for the zero-width joiner and non-joiner, which shape an emoji or a word, that an invalid byte is
replaced, and that a cut text ends with an ellipsis. A text from an editor therefore never breaks a page, while
an address, a locale or a handle that is not one throws `InvalidShareMetadata` where the page is built.

`fromArray()` refuses a key it does not know, so that a misspelt key fails rather than vanishes; a null value is a
missing one.

`ShareImage` checks its address as the page's, cuts its replacement text at 420 characters — the longest X keeps —,
takes a width and a height of 1 to 10,000 pixels, and a media type that is an image's, as `image/png`.

## The tags written

`MetaTags::of()` returns the tags in this order, each only when its value is there:

| Tags | From |
|---|---|
| `<link rel="canonical">`, `<meta name="description">` | The address, the description |
| `og:title`, `og:type`, `og:url`, `og:description`, `og:site_name`, `og:locale`, `og:locale:alternate` | The page |
| `og:image`, `og:image:type`, `og:image:width`, `og:image:height`, `og:image:alt` | The image |
| `article:published_time`, `article:modified_time`, `article:author` | An article's dates, in ISO 8601, and author |
| `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`, `twitter:image:alt`, `twitter:site`, `twitter:creator` | The page |
| `fediverse:creator` | The account credited on the fediverse, as Mastodon reads it |

The card of X is `summary_large_image` when the page has an image and `summary` otherwise, unless a `TwitterCard` is
passed. `MetaTags::html()` writes the tags one per line; every value is escaped for a double-quoted attribute, an
invalid byte replaced.
