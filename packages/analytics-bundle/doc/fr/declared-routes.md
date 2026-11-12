<!-- phil-dev source-sha256=612ef54fd962e6d48221bf5efc4f9da9bd3b24cf8b3b964b508fcc6c91591b6f -->
# Routes déclarées

**La page comptée est une route déclarée, jamais une URL.** Un site déclare une liste fermée de routes, et chaque
chemin que rapporte une page vue est ramené à l'ingestion au nom de la route qui lui correspond, ou à `(other)`.

État : `DeclaredRoute` et `DeclaredRoutes` sont implémentés et testés. La liste des routes de chaque site vit dans la
projection de collecte ([collection-and-admission.md](collection-and-admission.md)) : les `routes` de chaque site sous
`phil_analytics.sites` ([configuration.md](configuration.md#les-sites--sites)), ou la propre projection de
l'application.

## Pourquoi

- **La chaîne de requête et le fragment sont retirés** avant la correspondance. Ils peuvent porter un code
  d'autorisation et un état, un jeton de réinitialisation de mot de passe ou un jeton de désabonnement ; aucun n'est
  jamais lu ni gardé.
- **Un segment `{placeholder}` n'est jamais gardé.** `/u/{name}` compte tous les profils sous un seul nom de route et ne
  garde aucun nom d'utilisateur.
- **Un chemin qui ne correspond à aucune route déclarée est compté sous `(other)` à l'ingestion.** Un chemin forgé ne
  crée donc aucun nouveau compteur, et le nombre de compteurs par site est borné par le nombre de routes déclarées plus
  un.

## `DeclaredRoute`

```php
$route = DeclaredRoute::of('event', '/events/{slug}');
$route->name;      // 'event'
$route->pattern;   // '/events/{slug}'
```

| Règle | Détail |
|---|---|
| Nom | `CounterKey::NAME_PATTERN` : 1 à 64 caractères parmi `a-z`, `0-9`, `_`, `.`, `-`, commençant par une lettre. `(other)` n'est donc pas un nom de route valide |
| Motif | Commence par `/` |
| Segment paramètre | Un segment entier `{name}` où `name` correspond à `[A-Za-z_][A-Za-z0-9_]*`. Il correspond à **exactement un segment non vide** |
| Segment littéral | Caractères parmi `A-Za-z0-9._~!$&'()*+,;=:@%-`. Correspond à lui-même, **en respectant la casse** |
| Refusé | Un paramètre partiel (`x{slug}`, `{id}x`), non fermé (`{id`), des jokers (`*`, `?`), un saut de ligne final |

Autres méthodes publiques :

| Méthode | Retourne |
|---|---|
| `matches(list<string> $path): bool` | Si la route correspond à ces segments de chemin (même nombre, littéraux égaux, les paramètres prennent n'importe quoi) |
| `specificity(): int` | Le nombre de segments littéraux, compté une fois à la construction de la route |
| `length(): int` | Le nombre de segments d'un chemin qui correspond |
| `DeclaredRoute::split(string $path): list<string>` | Les segments d'un chemin, barre oblique finale ignorée : `/` n'a aucun segment, `/a/b/` a `a`, `b` |
| `hasSameShapeAs(DeclaredRoute $other): bool` | Si deux routes correspondent exactement aux mêmes chemins |

## `DeclaredRoutes`

```php
$routes = DeclaredRoutes::of([
    DeclaredRoute::of('home', '/'),
    DeclaredRoute::of('event', '/events/{slug}'),
    DeclaredRoute::of('events.archive', '/events/archive'),
    DeclaredRoute::of('profile', '/u/{name}'),
]);

$routes->normalize('/events/spring-con');                 // 'event'
$routes->normalize('/events/spring-con/');                // 'event' (trailing slash ignored)
$routes->normalize('/events/archive');                    // 'events.archive' (more literals win)
$routes->normalize('/events/spring-con?code=abc&state=x');// 'event' (query dropped)
$routes->normalize('/u/alice#token');                     // 'profile' (fragment dropped, name not kept)
$routes->normalize('/admin');                             // '(other)'
$routes->normalize('/events/spring-con/tickets');         // '(other)' (too deep)
$routes->normalize('/Events/archive');                    // '(other)' (literals are case-sensitive)
$routes->normalize('/events//x');                         // '(other)' (an empty segment never fills a placeholder)
$routes->normalize('events/x');                           // '(other)' (not an absolute path)

DeclaredRoutes::none()->normalize('/');                   // '(other)'
$routes->routes;                                          // list<DeclaredRoute>, in declaration order
```

Règles de normalisation, dans l'ordre :

1. Tout ce qui suit le premier `?` ou `#` est retiré.
2. Un chemin qui ne commence pas par `/` est `(other)`.
3. Un chemin avec un segment vide (`//`) est `(other)`.
4. Parmi les routes qui correspondent, celle qui a le plus de segments littéraux l'emporte ; à égalité, la première
   déclarée. Seules les routes qui ont autant de segments que le chemin sont essayées : `DeclaredRoutes` les range par
   nombre de segments, et une page vue coûte les routes de sa propre longueur, pas toutes les routes déclarées.
5. Aucune correspondance donne `(other)`.

`DeclaredRoutes::of()` refuse un nom de route déclaré deux fois, et deux routes de même forme (`/x/{id}` et
`/x/{slug}`), qui correspondraient exactement aux mêmes chemins.

Le nom retourné sert directement de valeur de dimension :

```php
$store->increment(CounterKey::of($site, $day, 'pageviews', 'route', $collectionSite->routes->normalize($path)));
```
