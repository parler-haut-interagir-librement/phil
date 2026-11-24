<!-- phil-dev source-sha256=73a120db1363a8d56d0ec4de8ed598d18dbd09ca5a1d947edef68cdd05b79709 -->
# phil/feed-bundle

**Atom, RSS et JSON Feed pour des nouvelles, des contenus ou des événements, écrits à partir d'une seule liste, annoncés
dans les pages, mis en cache par HTTP et signalés aux concentrateurs WebSub.**

phil/feed-bundle sert les flux d'une application Symfony. Un flux est un service qui implémente `FeedSource` : un nom,
un canal — titre, page, description, langue — et ses entrées, les plus récentes d'abord. Le bundle écrit chaque flux
en trois formats à partir de cette seule liste : Atom (RFC 4287), JSON Feed 1.1 et RSS 2.0 pour les lecteurs qui ne
lisent rien d'autre. Chacun est servi à `/feeds/{name}.atom`, `.json` et `.rss`, avec son type de média, un `ETag` et
un `Last-Modified` tirés de sa plus récente entrée, et un `304` pour un lecteur qui le détient déjà. Un flux paginé lie
ses pages comme le demande la RFC 5005. `phil_feed_links()` écrit les `<link rel="alternate">` des flux dans le
`<head>` d'une page. Quand une page de `phil/publishing` passe en ligne ou hors ligne, les flux qui la listent sont
signalés aux concentrateurs WebSub configurés, par Messenger et `phil/safe-http`, avec un message qui ne nomme que le
flux. Les contenus publiés d'un type de `phil/content-bundle` deviennent un flux avec quelques lignes de configuration.

Utilisez-le dans un site qui publie des nouvelles, des articles, des événements ou des épisodes et qui veut que des
lecteurs, des applications de balados et d'autres sites les suivent. Une source d'un autre genre — un calendrier, le
flux privé d'un partenaire — implémente `FeedSource` et est servie de la même façon.

**Ce qu'il ne fait pas** : pas de concentrateur WebSub à lui (l'application en nomme un public), pas de lecture des flux
d'autres sites, pas de résumé des nouvelles entrées par courriel.

Il se branche sur le routeur de Symfony, HttpKernel, Messenger, le répartiteur d'événements et Twig, et utilise
`phil/url` pour chaque adresse. Le XML est écrit par `XMLWriter`, le JSON par `json_encode()` ; aucune bibliothèque de
flux n'est utilisée.

## État

| Partie | État |
|---|---|
| `FeedSource`, les entrées et leurs vérifications | Implémenté et testé |
| Les écrivains Atom, RSS 2.0 et JSON Feed 1.1 | Implémenté, testé et soumis au fuzzing |
| `/feeds/{name}.{format}` : types de média, `ETag`, `Last-Modified`, `304`, en-têtes de cache, pages | Implémenté et testé |
| `phil_feed_links()` | Implémenté et testé |
| Annonces WebSub sur `PagePublished` et `PageRetired` | Implémenté et testé |
| Flux de contenus de `phil/content-bundle` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement, les routes et la recette Flex. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Sources](doc/fr/sources.md) | Écrire une `FeedSource` : le canal, les entrées, les pages, les pages de `phil/publishing` ; les flux de contenus. |
| [WebSub](doc/fr/websub.md) | Les concentrateurs, l'annonce, Messenger et ce qui est refusé. |
| [Points d'extension](doc/fr/extension-points.md) | Les sources, les écrivains, les adresses, les routes et ce qui reste fermé. |
| [Tests](doc/fr/testing.md) | Les tests du bundle et sa cible de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle, et les formats tels qu'écrits. |

## Démarrage rapide

```php
// config/bundles.php
Phil\FeedBundle\PhilFeedBundle::class => ['all' => true],
```

```yaml
# config/routes/phil_feed.yaml
phil_feed:
    resource: '@PhilFeedBundle/config/routes.php'
```

```php
use Phil\FeedBundle\Application\Contract\FeedSource;
use Phil\FeedBundle\Domain\ValueObject\Channel;
use Phil\FeedBundle\Domain\ValueObject\FeedItem;
use Phil\FeedBundle\Domain\ValueObject\FeedPage;
use Phil\FeedBundle\Domain\ValueObject\FeedQuery;
use Phil\Publishing\Contract\PageEvent;
use Phil\Url\Url;

final readonly class NewsFeed implements FeedSource
{
    public function __construct(private NewsRepository $news) {}

    public function name(): string { return 'news'; }

    public function channel(): Channel
    {
        return new Channel('News', Url::fromString('https://example.org/news'), language: 'en');
    }

    public function items(FeedQuery $query): FeedPage
    {
        $items = array_map(static fn (News $news): FeedItem => new FeedItem(
            'tag:example.org,2026:news-'.$news->id,
            $news->title,
            Url::fromString($news->url),
            $news->publishedAt,
            summary: $news->summary,
            contentHtml: $news->html,
        ), $this->news->latest($query->offset(), $query->limit + 1));

        return new FeedPage($items);
    }

    public function includes(PageEvent $page): bool
    {
        return str_starts_with($page->ownerId, 'news:');
    }
}
```

```twig
{# in the <head> of the layout #}
{{ phil_feed_links() }}
```

## Prérequis

PHP 8.5 ou une version plus récente avec mbstring et XMLWriter, Symfony 8.2 ou une version plus récente, Twig 3.21 ou
une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
