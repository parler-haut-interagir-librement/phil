# Pages

Three sources make the pages of the sitemap, in this order: the routes, the providers, the listeners of the populate
event.

## The route option

```php
#[Route('/', name: 'home', options: ['sitemap' => true])]
#[Route('/contact', name: 'contact', options: ['sitemap' => [
    'priority' => 0.8,
    'changefreq' => 'never',
    'lastmod' => '2025-02-07 21:00:00',
    'section' => 'pages',
]])]
```

```yaml
generator_names:
    path: /generators/names
    options:
        sitemap: { priority: 0.8, changefreq: yearly, lastmod: '2023-12-19 23:00:00', section: generators }
```

| Key | Default | |
|---|---|---|
| `priority` | `defaults.priority` | 0.0 to 1.0, in steps of 0.1 |
| `changefreq` | `defaults.changefreq` | as the protocol spells it |
| `lastmod` | `defaults.lastmod` | `now`, or a date read in the clock's time zone |
| `section` | `default_section` | |

`true` takes every default; `false`, or no option, leaves the route out. The page is the route's absolute URL, so the
route must make a URL without parameters: one that needs some — `/news/{slug}` — gives its pages with a provider. The
locales of a localized route are each a page, with every locale as an alternate. Every route is read whatever
section is asked for, so a bad option shows at once: an unknown key, a word where a number goes, an unknown
`changefreq`, a date that is none, a route that needs parameters — each is an `InvalidRouteOption` naming the route.

## Providers

```php
final readonly class NewsUrlProvider implements UrlProvider
{
    public function __construct(private NewsRepository $news, private UrlGeneratorInterface $urls) {}

    public function provide(UrlContainer $urls, ?string $section): void
    {
        if (null !== $section && 'news' !== $section) {
            return;
        }

        foreach ($this->news->published() as $news) {
            $urls->add(new Url($this->urls->generate('news_show', ['slug' => $news->slug], UrlGeneratorInterface::ABSOLUTE_URL), $news->updatedAt, ChangeFrequency::Never), 'news');
        }
    }
}
```

A service that implements `Phil\Sitemap\UrlProvider` is tagged `phil_sitemap.url_provider` when autoconfigured,
with `after: RouteUrlProvider::class`, so that the pages of the routes come first; a bundle tags its own, and names
`RouteUrlProvider::class` in the tag's `after` to keep that order. Asked for one section, a provider may return
early: the container keeps only that section anyway.

## The populate event

```php
#[AsEventListener]
public function __invoke(PopulateSitemap $event): void
{
    $event->urls->add(new Url('https://example.com/contact'), 'pages');
}
```

`$event->section` is the section asked for, or `null` for all of them.
