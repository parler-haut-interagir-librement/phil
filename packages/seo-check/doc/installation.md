# Installation

```sh
composer require phil/seo-check
```

The package needs PHP 8.5 or later, with the extensions `dom`, `libxml`, `mbstring` and `uri`, and requires
phil/publishing, phil/url and symfony/translation 8.2. Two libraries add a rule each when they are installed, and a
third gives the rule broken-links its statuses:

```sh
composer require phil/structured-data   # the rule structured-data: the JSON-LD of the page, validated
composer require phil/social-cards      # the rule share-tags: the Open Graph and X tags of the page
composer require phil/link-checker      # LinkCheckerStatuses: the statuses of the links, from its checker
```

## Checking one page

A page is checked from its renderings: the page at its canonical address first, then, when there are any, the other
paths that lead to it and its renderings for each storefront or channel.

```php
use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\RenderedPage;
use Phil\SeoCheck\SeoChecker;
use Phil\Url\Url;

$canonical = Url::fromString('https://example.org/fr/');

$page = CheckedPage::of($canonical, 'fr', true, [
    new RenderedPage($canonical, $html, 200, ['Content-Language' => 'fr']),
    new RenderedPage(Url::fromString('https://example.org/'), $homeHtml),  // the same page, by another path
]);

$report = new SeoChecker()->check($page, 'fr');

foreach ($report->findings() as $finding) {
    echo $finding->severity->value, ' ', $finding->rule, ': ', $report->message($finding), "\n";
}
```

`new SeoChecker()` runs the default rules without the ports: no reciprocity of `hreflang` and no dead links. Give it
the rules to run to add them:

```php
use Phil\SeoCheck\Bridge\SocialCards\ShareTagsRule;
use Phil\SeoCheck\Bridge\StructuredData\StructuredDataRule;
use Phil\SeoCheck\Rule\DefaultRules;

$checker = new SeoChecker([
    ...DefaultRules::create($renderedPages, $linkStatuses),
    new StructuredDataRule(),
    new ShareTagsRule(),
]);
```

## Checking each page as it is published

phil/publishing dispatches `PagePublished` when a page goes online: its canonical address, its language, its flag
« indexable » and the identifier of what owns it. `PageCheckFactory` builds the page to check from that event and the
application's `RenderedPages`, which renders the page without a request over the network.

A listener in an application, or in a bundle, keeps the report where whoever published the page reads it:

```php
use Phil\Publishing\PagePublished;
use Phil\SeoCheck\PageCheckFactory;
use Phil\SeoCheck\Render\JsonRenderer;
use Phil\SeoCheck\SeoChecker;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class CheckPublishedPage
{
    public function __construct(
        private PageCheckFactory $pages,
        private SeoChecker $checker,
        private SeoReports $reports,   // the application's own storage
    ) {
    }

    public function __invoke(PagePublished $event): void
    {
        $page = $this->pages->fromEvent($event);

        if (null === $page) {
            return;   // the application renders none of it
        }

        $report = $this->checker->check($page, $event->locale);   // or the language of the administration
        $this->reports->save($event->ownerId, new JsonRenderer()->render($report));
    }
}
```

The services, in a Symfony application:

```yaml
# config/services.yaml
services:
    Phil\SeoCheck\Contract\RenderedPages: '@App\Seo\KernelRenderedPages'
    Phil\SeoCheck\PageCheckFactory: ~
    Phil\SeoCheck\SeoChecker:
        arguments:
            $rules: !tagged_iterator phil_seo_check.rule

    _instanceof:
        Phil\SeoCheck\Contract\SeoRule:
            tags: ['phil_seo_check.rule']
```

Each rule is then a service of the application, tagged `phil_seo_check.rule` and registered in the order of the report,
the order `DefaultRules::create()` gives. The check never stops the publication and never throws for a page it finds
wrong: a rule that fails, a port that cannot answer, gives a warning in the report, and the other rules still run.

The check reads each rendering once, at a cost bounded by the size of the page; for long pages, or many renderings,
run it from a Messenger handler instead, the message carrying the page's identifier only, and the handler building
the `PagePublished` fields again from it.

## Messages

The messages are keys of the translation domain `phil_seo_check`, written in English and French by
`Translation\Messages::translator()`, English the fallback. An application that has its own translator gives it to
`SeoChecker`, after loading `Messages::resources()` into it, and adds its own languages there.
