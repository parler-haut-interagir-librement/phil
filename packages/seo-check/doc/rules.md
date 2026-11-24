# Rules

The default rules follow the SEO audits of Lighthouse, in the order of the report: the response, the head of the page,
its body, its links. `DefaultRules::create()` gives them; the rules of `Bridge\` are added when their libraries are
installed.

Each finding has a severity:

| Severity | Meaning |
|---|---|
| `error` | Search engines misread the page, or cannot index it as it was published: correct it soon. |
| `warning` | The page is indexed, but shown or reached worse than it could be. |
| `info` | Worth knowing, perhaps intended: nothing has to change. |

None of them stops a publication. The check leaves out on purpose what does not help a search engine read a page:
keyword density, readability scores and a single overall score.

A finding quotes the page by excerpts: an address by its path and query, a value cut at 80 characters.

## `http-status`

Every rendering answers with a success: search engines drop a page that answers with an error.

| Finding | Severity |
|---|---|
| A rendering answers with a status of 400 or more | error |
| The page at its own address answers with a redirect: its canonical address is where readers land | warning |

Another path to the page may redirect. The rules that read the HTML of a rendering — `canonical`, `indexing` — leave a
rendering that redirects or fails to this rule.

## `page-size`

The page is no larger than the check reads, 5,000,000 bytes by default: what lies beyond was not checked, and search
engines stop reading a page long before its end too. A warning.

## `document-title`

The page has one `<title>`, not empty, of a length a results page shows whole: it is the link readers click.

| Finding | Severity |
|---|---|
| No `<title>`, or an empty one: results pages show the address instead | error |
| Several `<title>`: search engines read the first one only | warning |
| Fewer than 10 characters, or more than 60, counted as characters, not bytes | warning |

The bounds are arguments of `DocumentTitle`.

## `meta-description`

The page has one `<meta name="description">`, not empty: results pages show it under the title, and write a summary of
their own, from whatever text they find, when it is missing.

| Finding | Severity |
|---|---|
| No meta description, an empty one, or several | warning |
| Fewer than 50 characters, or more than 160 | info |

The length is information only: a results page cuts a long description, and a short one says little. The bounds are
arguments of `MetaDescription`.

## `document-language`

The `lang` of the `<html>` element is the language the page was published in: search engines show a page to readers
of its language, and screen readers read it with that language's voice. Language tags compare as BCP 47 does, without
regard to case, `fr_CA` read as `fr-CA`.

| Finding | Severity |
|---|---|
| No `lang`, or an empty one | error |
| Another language | error |
| The same language, another region or script: `fr` for a page published in `fr-CA` | warning |
| A `Content-Language` header that names neither the page's language nor its primary language | warning |

## `single-h1`

The page has one first-level heading, with text: it says what the page is about, to readers and to search engines.
None, several, or one without text, is a warning.

## `canonical`

The page names one canonical address, absolute, and it is the address the page was published at: search engines gather
what they know of a page under its canonical address, and index the address it names rather than the one they read.

| Finding | Severity |
|---|---|
| No canonical link | error |
| A relative canonical link: it is resolved, then compared, but should be written absolute | warning |
| A canonical link that is not an address search engines can follow | error |
| Several canonical links naming different addresses: search engines ignore them all | error |
| A canonical address other than the one the page was published at | error |

Every other rendering — another path to the same page, `/` and `/fr/` for a home page, or a channel — is held to the
same canonical address. A path that names another, or none, splits the page in two for search engines: the finding
names the path.

## `indexing`

The indexing directives of each rendering say what the page was published as. The directives are read from
`<meta name="robots">`, from the `<meta>` named after a crawler — `googlebot`, `bingbot` and the others of
`RobotsDirectives::AGENTS` —, and from the `X-Robots-Tag` headers, whose value may start with the crawler it is for.
`noindex` and `none` keep a page out of the index.

| Finding | Severity |
|---|---|
| A page published as not indexable that no directive keeps out of the index of every crawler | error |
| A page published as indexable that a `noindex` or `none` for every crawler keeps out | error |
| A page published as indexable that a `noindex` for one crawler keeps out of that crawler's index | warning |

When the application serves the page in several storefronts or channels, each rendering is held to what the
application says of its channel, through `ChannelIndexing`: a product not sold in one storefront, a channel kept away
from search engines. A channel the application does not name follows the page.

## `hreflang`

The translations a page lists with `<link rel="alternate" hreflang="…">` are valid and reciprocal: search engines
ignore a pair of translations that do not list each other.

| Finding | Severity |
|---|---|
| A code that is not a BCP 47 language tag, nor `x-default` | error |
| A relative address, or one search engines cannot follow | error |
| One language leading to several addresses | error |
| The page does not list itself, or lists itself under another language than its own | warning |
| A translation that does not list the page back | warning |

`x-default` is optional. A page that lists no translation passes. Reciprocity is read through `RenderedPages`, 50
translations at most by default; a translation the application does not render is not reported, and without
`RenderedPages` reciprocity is not checked.

## `crawlable-links`

Search engines follow the links of a page by their `href`. A link with text but no `href`, or one that runs a script,
`javascript:…` — in any case, or split by tabs and line breaks a browser drops —, leads them nowhere: a warning. An
`<a>` without `href` nor text is an anchor, not a link.

## `link-text`

The text of a link says where it leads: search engines read it as a description of the page it links to, and a reader
skimming a page reads only its links. A text such as « click here », « en savoir plus » or « leer más » is a warning.

The texts are compared in lower case, their spaces collapsed, the punctuation around them left out. The lists are
those of `GenericLinkTexts::DEFAULTS`, for English, French, Spanish, German, Italian and Portuguese, chosen by the
page's primary language; an application gives `LinkText` its own lists. A page of a language without a list passes.

## `image-alt`

Every image has an `alt` attribute: search engines read it as the image's text, as screen readers do. An image without
one is a warning. An empty `alt=""` is right for an image that is only decoration, and so is an image hidden from
readers by `aria-hidden="true"` or a role of `presentation` or `none`.

## `broken-links`

The links of the page lead somewhere: a link that answers with an error wastes the visit of a search engine and the
patience of a reader. The statuses come from the application's `LinkStatuses`, as a link checker last saw them; the
check requests nothing, and the rule runs only when `LinkStatuses` is given.

| Finding | Severity |
|---|---|
| A link that answers with a status from 400 to 499 | error |
| A link that answers with a status of 500 or more: its server may come back | warning |
| A link that redirects: link to where it leads | info |

Each address is reported once, its fragment dropped; 1,000 addresses at most are asked about by default. A link the
application does not know is not reported. With phil/link-checker, `Bridge\LinkChecker\LinkCheckerStatuses` gives the
statuses its checker found: see [Extension points](extension-points.md#linkstatuses).

## `structured-data`

With phil/structured-data, `Bridge\StructuredData\StructuredDataRule` validates the JSON-LD of the page with its
`Validator`: an error of the validator is an error, a warning a warning, an element that cannot be read an error. A
page without structured data is an information: rich results need it. The message of each issue is the validator's,
in English, given as the `{message}` placeholder, with its code as `{code}` and the node it is about as `{node}`.

## `share-tags`

With phil/social-cards, `Bridge\SocialCards\ShareTagsRule` checks the share tags of the page with its `TagCheck`: the
tags Open Graph requires, absolute addresses, a description, an image with its text and its size, a card of X, and
`og:url` agreeing with the canonical link. An error of the check is an error, a warning a warning; the message is the
check's, in English, given as the `{message}` placeholder, and the tag as `{tag}`.

## When a rule fails

A rule that throws — a port that cannot answer — gives the warning `check.rule-failed`, naming the rule and the class
of the exception, never its message; the other rules still run.
