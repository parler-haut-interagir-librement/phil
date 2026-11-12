<!-- phil-dev source-sha256=889c021f8b5066120b72402c4646ef334ecab9bf9db4355c43eb39c696045779 -->
# Routage

Le contenu publié a des adresses : une **table de routes** contient le slug de chaque racine dans chaque locale, le
**`ContentController`** les sert, et les anciens slugs redirigent de façon permanente.

## La table de routes

`Domain\Routing\Route` est une entité mappée sur `phil_content_route`, une ligne par adresse :

| Colonne | Contient |
|---|---|
| `id` | `phil_content_route_id`, un UUIDv7 généré à la construction de la route |
| `resource_key` | le genre de contenu (`VARCHAR(64)`) |
| `locale` | `phil_content_locale` |
| `slug` | `VARCHAR(255)` |
| `resource_id` | l'identifiant de la racine vers laquelle elle pointe, en chaîne (`VARCHAR(64)`) |
| `history` | `false` pour la route actuelle, `true` pour un ancien slug |

Elle est **unique sur `(resource_key, locale, slug)`** : deux genres de contenu peuvent donc partager un slug, et un
genre ne peut pas utiliser deux fois un slug dans une locale. Elle est indexée sur `(resource_key, resource_id, locale)`
pour les recherches par contenu.

`Route` expose `current(ContentReference, Locale, Slug)` (son seul constructeur), `id()`, `content()`, `locale()`,
`slug()`, `isHistory()`, `retire()` et `pointTo(ContentReference)` ; `pointTo()` refuse une référence d'un autre genre
(`InvalidContent`).

### Invariants

- **Les routes décrivent ce qui est servi aux lecteurs.** Elles sont écrites quand une ligne localisée est publiée, à
  partir de son slug, jamais pendant sa modification. La publication fait du slug la route actuelle de la racine dans
  cette locale ; la route actuelle précédente passe à l'historique.
- **Un ancien slug redirige de façon permanente** vers la route actuelle de la même racine et de la même locale. Une
  route d'historique n'enregistre aucune cible : la route actuelle est cherchée à l'arrivée de la requête, donc une
  chaîne de renommages ne redirige qu'une fois.
- **Un slug revient à sa racine** : publier un slug que la racine a déjà utilisé fait revivre sa route d'historique.
  Une route d'historique d'une autre racine du même genre cède son slug, et l'ancienne adresse sert alors le nouveau
  contenu.
- **Dépublier garde la route**, pour que l'adresse ne soit pas donnée à une autre racine ; elle répond 404 jusqu'à ce
  que la locale soit publiée de nouveau.
- **Supprimer une racine libère ses adresses.** `ContentManager::delete()` retire les routes avec les lignes et la
  racine. Une route laissée par une racine supprimée autrement est libre quand même, et reprise comme une route
  d'historique.

### `RouteUpdater`

| Méthode | Ce qu'elle fait |
|---|---|
| `assertAvailable(ContentReference, Locale, Slug)` | passe quand le slug est libre dans sa locale, appartient à la racine elle-même, est une route d'historique d'une autre racine, ou la route d'une racine qui n'existe plus ; lève `SlugTaken` sinon. `ContentEditor` l'appelle à chaque modification qui définit un slug |
| `assign(ContentReference, Locale, Slug)` | fait du slug la route actuelle (en faisant revivre ou en reprenant une route existante, ou en en ajoutant une) et retire la route actuelle précédente ; rien ne se passe quand le slug est déjà actuel. `ContentPublisher` l'appelle à la publication |

### `RouteResolver`

`resolve(string $resourceKey, FallbackChain $chain, string $slug): ?RouteMatch` cherche le slug dans chaque locale de
la chaîne, dans l'ordre : une page demandée en `fr_CA` qui existe seulement en `fr` est trouvée par son slug `fr`.

- une route actuelle répond `RouteMatch::serve($route)` (`$match->redirect` vaut `false`) ;
- une route d'historique répond `RouteMatch::redirectTo($current)` avec la route actuelle de la même racine et de la
  même locale, ou `null` quand cette racine n'a plus de route actuelle ;
- une chaîne qui n'est pas un slug valide, ou un slug qu'aucune locale de la chaîne ne contient, répond `null`.

## Monter le contrôleur

L'application monte `Phil\ContentBundle\Infrastructure\Http\ContentController` une fois par genre de contenu, avec un
paramètre `{slug}`, et nomme la route sous `phil_content.routes` :

```yaml
# config/routes/content.yaml
news_show:
    path: /{_locale}/news/{slug}
    controller: Phil\ContentBundle\Infrastructure\Http\ContentController

page_show:
    path: /{_locale}/{slug}
    controller: Phil\ContentBundle\Infrastructure\Http\ContentController
    priority: -10
```

```yaml
# config/packages/phil_content.yaml
phil_content:
    routes:
        news: news_show
        static_page: page_show
```

La locale est celle de la requête (`Request::getLocale()`) : mettez `{_locale}` dans les chemins, utilisez des routes
localisées ou — pour un site à une seule locale — assurez-vous que `framework.default_locale` est une locale déclarée
sous `phil_content.locales`. Une route attrape-tout comme `/{_locale}/{slug}` reçoit une priorité basse, pour que les
autres routes de l'application correspondent d'abord.

### Routes à adresse fixe

Un genre peut aussi être monté sur des routes à adresse fixe, une page chacune, avec le slug comme valeur par défaut
de la route. Elles sont listées **après** la route sur laquelle les URL du genre sont générées, celle qui prend le
paramètre `{slug}`. Ainsi, une page garde le nom de route vers lequel pointent ses gabarits, et chacune de ses URL est
toujours générée sur la première route.

```yaml
# config/routes/content.yaml
privacy:
    path: /{_locale}/privacy
    controller: Phil\ContentBundle\Infrastructure\Http\ContentController
    defaults: { slug: privacy }
```

```yaml
# config/packages/phil_content.yaml
phil_content:
    routes:
        static_page: [page_show, privacy]   # page_show generates; privacy serves /privacy as well
```

Une route sert un seul genre de contenu : une route listée sous deux clés, ou deux fois, est refusée à la construction
du conteneur. `ResourceRoutes` contient la correspondance : `routeOf($resourceKey)` est la route de génération,
`resourceKeyOf($route)` le genre que sert une route.

## Ce que répond le contrôleur

Le contrôleur lit le genre de contenu dans la route qui a correspondu (son `_canonical_route` pour une route localisée)
et la locale dans la requête, puis :

1. **404** quand la route ne sert aucun genre sous `phil_content.routes`, ou que la locale de la requête est mal
   formée ou non servie ;
2. résout le slug le long de la chaîne déclarée de la locale ; **404** pour un slug inconnu ;
3. **301** vers le slug actuel pour un slug d'historique — sur la même route, ou sur la route de génération quand la
   route qui a correspondu a une adresse fixe ;
4. lit le contenu en ligne de la racine le long de la chaîne ; **404** quand la racine n'existe plus ou qu'aucune
   locale de la chaîne ne la publie ;
5. **un contenu, une adresse** : quand la locale servie n'est pas celle dont la route a correspondu — le slug `fr`
   demandé en `fr_CA`, alors que la racine publie `fr_CA` sous son propre slug —, répond **301** vers le slug de la
   locale servie. Une locale servie sans route propre n'a pas d'adresse : le contenu est alors lu à partir de la
   locale de la route qui a correspondu, le long du reste de la chaîne ;
6. **200** : produit le rendu du gabarit de la clé de gabarit de la ligne, ou du gabarit par défaut
   ([rendering.md](rendering.md)).

Une redirection garde les autres paramètres de la route (`_locale`, …) et remplace `slug`.

Une clé de gabarit que personne n'a déclarée, sans gabarit par défaut, est une erreur de configuration : la
`LogicException` de `TemplateResolver` n'est pas transformée en 404.

## Générer des URL

`Infrastructure\Http\ContentUrlGenerator::generate(ContentReference $content, Locale $locale, bool $absolute = false): ?string`
renvoie l'URL de la route actuelle de la racine dans la locale, sur la route de génération de son genre, ou `null`
quand la racine n'a pas de route actuelle dans la locale ou que son genre n'est monté sur aucune route.

`_locale` est passé seulement quand il diffère du `_locale` de la requête en cours (le contexte du routeur). Ainsi, un
site à une seule locale et sans `{_locale}` dans ses chemins ne reçoit jamais de requête `?_locale=`. Un site à
plusieurs locales monte ses routes avec `{_locale}`, ou comme routes localisées, que le générateur choisit par
`_locale`.

Dans les gabarits, le même générateur répond à `content_url()` et à `content_link_url()`
([rendering.md](rendering.md)).
