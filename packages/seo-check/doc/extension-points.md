# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Add a check | Implement `Contract\SeoRule`, and give it to `SeoChecker` with the other rules |
| Give the check the HTML of a page | Implement `Contract\RenderedPages` |
| Report dead links | Implement `Contract\LinkStatuses` from what a link checker last saw |
| Say whether a page is indexable in each channel | Implement `Contract\ChannelIndexing` |
| Write the report in another format | Implement `Contract\ReportRenderer` |
| Write the messages in another language | Add the keys of the domain `phil_seo_check` to a translator |
| Change a bound | Pass it to the rule's constructor: lengths, generic link texts, crawlers, limits |

## Writing a rule

A rule reads the page and returns what it finds. It never changes the page, keeps nothing from one page to the next —
one instance serves every request of a worker —, and never throws for a page it finds wrong: an exception is reported
by `SeoChecker` as the warning `check.rule-failed`.

```php
use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Contract\SeoRule;
use Phil\SeoCheck\Finding;
use Phil\SeoCheck\Message;
use Phil\SeoCheck\Severity;

final readonly class ViewportRule implements SeoRule
{
    public function name(): string
    {
        return 'viewport';
    }

    public function check(CheckedPage $page): iterable
    {
        if ([] === $page->primary()->markup->meta('viewport')) {
            yield new Finding('viewport', Severity::Warning, new Message('viewport.missing'));
        }
    }
}
```

`CheckedPage` gives the page as it was published — `canonicalUrl`, `locale`, `indexable`, `isIndexableIn($channel)` —
and its renderings: `primary()` is the page at its own address, `renderings` every one. A `Rendering` holds the
`RenderedPage` — address, HTML, status, headers, channel — and the `PageMarkup` read from it once: titles, `<meta>` by
name, document language, first-level headings, canonical and alternate links, links and images. A rule that needs
something else of the HTML parses `$rendering->page->html` itself, at a bounded cost.

The name is kebab case and the same for every finding. The message is a key and the values of its placeholders; add
the key to the translator, or the report writes the key as it is:

```php
use Phil\SeoCheck\SeoChecker;
use Phil\SeoCheck\Rule\DefaultRules;
use Phil\SeoCheck\Translation\Messages;

$translator = Messages::translator();
$translator->addResource('array', ['viewport.missing' => 'The page has no viewport.'], 'en', Messages::DOMAIN);

$checker = new SeoChecker([...DefaultRules::create(), new ViewportRule()], $translator);
```

A finding about one thing of the page — a link, an image, a path — names it as its `subject`, quoted by an excerpt.

## The ports

### `RenderedPages`

Where the check gets the HTML of a page, as the application renders it, without a request over the network: a
sub-request to the kernel, a render of the page's template, a page cache.

- `renderingsOf(PagePublished $event)`: the page at its canonical address first, then the other paths that lead to it
  — `/` and `/fr/` for a home page — and its rendering for each channel, each a `RenderedPage` with its channel. None
  when the page cannot be rendered: `PageCheckFactory::fromEvent()` then returns null.
- `renderingAt(Url $url)`: another page of the application, as the `hreflang` rule reads the translations of a page;
  null when the address is not one of the application's pages.

A `RenderedPage` keeps the headers search engines read — `X-Robots-Tag`, `Content-Language`, `Content-Type` for the
character set —; give it those of the response.

### `LinkStatuses`

What the application knows of the links of a page: the status code each answered with, as a link checker last saw it,
by the address `Url::toString()` writes. The rule `broken-links` asks once per page, with the page's links without
their fragments, and reports nothing for a link left out. An adapter for a link checker reads its last results; it
never requests the links while the page is checked.

`Bridge\LinkChecker\LinkCheckerStatuses` is that adapter for phil/link-checker. Give it the application's
`LinkChecker`, built with a cache, so that the links it checked lately are read from the cache rather than requested
again:

```php
use Phil\LinkChecker\LinkChecker;
use Phil\LinkChecker\Probe\HttpLinkProbe;
use Phil\SeoCheck\Bridge\LinkChecker\LinkCheckerStatuses;
use Phil\SeoCheck\Rule\DefaultRules;

$statuses = new LinkCheckerStatuses(new LinkChecker(new HttpLinkProbe(), cache: $cache));
$rules = DefaultRules::create($renderedPages, $statuses);
```

What the checker found becomes the status code the rule reads:

| The checker found | The status code |
|---|---|
| Alive, dead or to review, with an answer | The code of the answer; to review, only a `5xx` — `401`, `403` and `429` are left out, as the checker never counts them as dead |
| Dead, its host does not exist | `404` |
| A redirect, every step permanent | `301` |
| A redirect with a temporary step | `302` |
| Refused, unreadable, or to review without an answer, such as a timeout | Left out |

The links reach the checker as the links of one content at `LinkCheckerStatuses::PAGE`, an address of the reserved
`.invalid` domain that no link names; the checker never requests that page. A request of a link not in the cache
happens while the page is checked: for a long page, check it in a Messenger handler whose message carries the page's
identifier only.

### `ChannelIndexing`

Whether a page is indexable in each storefront or channel the application serves it in, asked once per channel by
`PageCheckFactory`; null when the channel follows what the page was published as.

### `ReportRenderer`

Writes a report in one format. `TextRenderer`, `JsonRenderer` and `HtmlRenderer` are given; the HTML one carries the
classes `isError`, `isWarning` and `isInfo`, no style and no script, for the administration's own stylesheet.

## Messages

`Translation\Messages` holds the messages in English and French; `Messages::resources()` gives them by locale, for an
application to load into its own translator, under the domain `phil_seo_check`. Placeholders are written `{name}`.
