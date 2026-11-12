<!-- phil-dev source-sha256=8c0241d67ca23492d6f8ec6a2ddfd7f6b1dfa048bdcbf1dc38ab47b5092fd4b4 -->
# Plan du site, flux et aperçus

Deux points de terminaison lisent ce qui est publié, un autre ce qui ne l'est pas encore, et un fournisseur donne ses
pages au plan du site. Ils sont enregistrés avec DoctrineBundle et TwigBundle :

```yaml
# config/routes/phil_content.yaml
phil_content:
    resource: '@PhilContentBundle/config/routes.php'
```

| Route | Chemin | Contrôleur |
|---|---|---|
| `phil_content_feed` | `/feeds/{resourceKey}.atom` | `FeedController` |
| `phil_content_preview` | `/preview/{reference}/{locale}` | `PreviewController` |

## Ce que publie une locale

`PublishedContents` lit les contenus d'un genre qui ont une ligne en ligne **dans une locale** — sans repli : un plan
du site liste les pages propres à une locale, un flux ses propres entrées. Chacun est lu dans la dernière version à
partir de laquelle cette locale a été publiée, fusionné comme sa page le montre. Il les lit par
`PublishedRowRepository`, une page de contenus en quelques requêtes :

| Méthode | Lit |
|---|---|
| `find(PublishedRowQuery $query)` | une page de contenus, filtrée et triée comme le dit la requête ([extension-points.md](extension-points.md#publishedrowrepository)) |
| `chunks($resourceKey, $locale, $size = 500)` | chaque contenu du genre, en listes d'au plus `$size`, par racine : les requêtes croissent avec le nombre de morceaux, pas de contenus |
| `latest($resourceKey, $locale, $limit, $withSlug = false)` | les plus récemment publiés d'abord, ceux qui n'ont jamais été publiés à la fin ; avec `$withSlug`, seulement ceux qu'une route peut servir |

Chaque `PublishedContent` donne :

| Méthode | Valeur |
|---|---|
| `publishedAt()` | le moment de sa dernière publication (`WorkflowCapable`), ou `null` |
| `title()` | le titre de l'extrait, sinon le titre de référencement, sinon la référence |
| `summary()` | la description de l'extrait, sinon la description de référencement, ou `null` |
| `isHiddenFromSitemaps()` | `seoHideInSitemap` ou `seoNoIndex` |

## Le plan du site

`phil/sitemap-bundle` sert et vide le plan du site. `ContentUrlProvider`, un `Phil\Sitemap\UrlProvider` étiqueté
`phil_sitemap.url_provider` après le `RouteUrlProvider` du plan du site, lui donne les contenus, après les pages des
routes. Il crée une section par genre sous `phil_content.routes` — `news`, `page`. Un genre dont la clé se termine par
un trait de soulignement et des chiffres, comme `news_2026`, devient la section `news-2026`
(`Sitemap::sectionNamed()`), puisque cette terminaison nomme les parties numérotées d'une section. Chaque contenu y
figure dans chaque locale servie à son URL absolue (`ContentUrlGenerator`), avec le moment de sa dernière publication
comme `lastmod`. Quand le même contenu est publié dans d'autres locales, chaque locale figure comme variante —
`fr_CA` s'écrit `fr-CA`. Un contenu caché des plans du site, ou sans URL dans la locale, est omis. Chaque locale est
lue par morceaux, et les URL d'un morceau en une seule recherche de routes (`ContentUrlGenerator::generateAll()`). Sans
`phil/sitemap-bundle`, le fournisseur est enregistré et rien ne l'interroge.

## Les flux

`/feeds/news.atom`, ou une route de l'application avec `{_locale}` et une valeur par défaut `title` :

```yaml
news_feed:
    path: /{_locale}/feeds/{resourceKey}.atom
    controller: Phil\ContentBundle\Infrastructure\Http\FeedController
    defaults: { title: 'News' }
```

Le flux contient les `feed_limit` dernières entrées du genre dans la locale de la requête qui ont un slug, lues en une
seule page, leurs URL en une seule recherche de routes. Chacune a son URL comme `<id>`, son titre, son résumé et le
moment de sa publication ; le `<updated>` du flux est le plus récent d'entre eux. Un genre monté sur aucune route, ou
une locale non servie, donne un `404`.

## Les liens d'aperçu

```php
$url = $links->url($reference, Locale::fromString('fr'));   // PreviewLinks
```

```bash
bin/console phil:content:preview-link news:0192f3c4-… fr
```

Un lien porte son moment d'expiration — `preview.ttl` secondes plus tard — et un HMAC-SHA256 de la référence, de la
locale et de ce moment, signé avec `preview.secret`. `PreviewController` produit le rendu du dernier brouillon du
contenu dans la locale par le gabarit de sa clé, avec les variables d'une page en ligne et `preview` à `true`, envoyé
avec `X-Robots-Tag: noindex, nofollow` et `Cache-Control: no-store, private`. Un lien non signé, expiré, pour un autre
contenu ou une autre locale, ou pour un contenu sans brouillon à cet endroit, donne un `404`.

## Ce qui n'est pas ici

La publication à une heure fixée n'est pas intégrée : la publication passe par `ContentManager::publish()`, qu'une
tâche planifiée de l'application peut appeler.
