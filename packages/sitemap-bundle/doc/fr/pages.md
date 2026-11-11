<!-- phil-dev source-sha256=8a1651bbbed7c208ea400aa3a0869c82d5a0370cc1bea93a710a57687f3a6233 -->
# Pages

Trois sources produisent les pages du plan de site, dans cet ordre : les routes, les fournisseurs, les écouteurs de
l'événement de remplissage.

## L'option de route

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

| Clé | Valeur par défaut | |
|---|---|---|
| `priority` | `defaults.priority` | De 0,0 à 1,0, par pas de 0,1 |
| `changefreq` | `defaults.changefreq` | telle que l'écrit le protocole |
| `lastmod` | `defaults.lastmod` | `now`, ou une date lue dans le fuseau horaire de l'horloge |
| `section` | `default_section` | |

`true` prend toutes les valeurs par défaut ; `false`, ou aucune option, laisse la route de côté. La page est l'URL
absolue de la route : la route doit donc produire une URL sans paramètres. Une route qui en a besoin —
`/news/{slug}` — donne ses pages par un fournisseur. Chaque locale d'une route localisée est une page, avec toutes les
locales comme variantes. Chaque route est lue quelle que soit la section demandée, si bien qu'une mauvaise option se
voit tout de suite : une clé inconnue, un mot là où va un nombre, un `changefreq` inconnu, une date qui n'en est pas
une, une route qui a besoin de paramètres — chacune est une `InvalidRouteOption` qui nomme la route.

## Fournisseurs

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

Un service qui implémente `Phil\Sitemap\UrlProvider` reçoit l'étiquette `phil_sitemap.url_provider` quand il est
autoconfiguré, avec `after: RouteUrlProvider::class`, pour que les pages des routes viennent en premier ; un bundle
étiquette les siens, et nomme `RouteUrlProvider::class` dans le `after` de l'étiquette pour garder cet ordre. Quand on
lui demande une section, un fournisseur peut retourner plus tôt : le conteneur ne garde de toute façon que cette
section.

## L'événement de remplissage

```php
#[AsEventListener]
public function __invoke(PopulateSitemap $event): void
{
    $event->urls->add(new Url('https://example.com/contact'), 'pages');
}
```

`$event->section` est la section demandée, ou `null` pour toutes.
