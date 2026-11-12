<!-- phil-dev source-sha256=65eb9cb936bbc4bcdfb28ef4420fd9d86c18c9e4ba68778da8668d1938049ce4 -->
# Deprecation et Sunset

**État :** `DeprecationSignal`, `HttpDate` et `LinkHeader` sont implémentés et testés; `ObservedDeprecations` est
seulement un port.

## La règle

Un contrat sans version ne publie jamais de `/v2`. Un changement qui ne peut pas être un ajout devient une **nouvelle
ressource à côté de l'ancienne**, et l'ancienne **annonce son propre retrait dans chaque réponse** jusqu'à sa
suppression :

| En-tête | Spécification | Valeur |
|---|---|---|
| `Deprecation` | RFC 9745 | La date où la ressource a été, ou sera, marquée obsolète, sous forme de date de champ structuré : `@` suivi d'un horodatage Unix, par exemple `@1688169599`. |
| `Sunset` | RFC 8594 | La date où la ressource cesse d'être servie, sous forme de date HTTP (RFC 9110, section 5.6.7), par exemple `Sun, 31 Dec 2023 23:59:59 GMT`. |
| `Link` | RFC 8288 | `<uri>; rel="deprecation"`, le document qui indique quoi faire à la place. |

Le préavis minimal entre les deux dates, et qui les décide, relèvent de l'application : `noticeInDays()` donne le
chiffre, la bibliothèque ne le juge pas.

## `DeprecationSignal`

`Phil\ApiContract\Deprecation\DeprecationSignal` est un objet-valeur en lecture seule avec trois propriétés publiques :
`?DateTimeImmutable $deprecatedAt`, `?DateTimeImmutable $sunsetAt`, `?string $link`.

### Côté serveur : `of()` et `toHeaders()`

```php
public static function of(?DateTimeImmutable $deprecatedAt, ?DateTimeImmutable $sunsetAt = null, ?string $link = null): self
```

`of()` lève `InvalidContractValue` quand :

- les deux dates sont `null` (un signal porte une date d'obsolescence, une date de retrait, ou les deux);
- le retrait précède l'obsolescence (des dates égales sont permises);
- le lien est vide, ou contient `>`, une espace ou un caractère de contrôle : il briserait l'en-tête `Link` ou
  injecterait un autre en-tête.

Les deux dates sont tronquées à la seconde entière, puisque les deux en-têtes portent des secondes entières. Un signal
est donc égal à son propre aller-retour.

```php
use Phil\ApiContract\Deprecation\DeprecationSignal;

$signal = DeprecationSignal::of(
    new DateTimeImmutable('@1688169599'),
    new DateTimeImmutable('2023-12-31 23:59:59 UTC'),
    'https://example.com/guide/deprecations#tenants',
);

foreach ($signal->toHeaders() as $name => $value) {
    $response->headers->set($name, $value);
}
// Deprecation: @1688169599
// Sunset: Sun, 31 Dec 2023 23:59:59 GMT
// Link: <https://example.com/guide/deprecations#tenants>; rel="deprecation"
```

`toHeaders()` retourne un `array<string, string>` indexé par nom d'en-tête et **n'écrit aucun en-tête pour une partie
absente**. Si la réponse porte déjà d'autres valeurs `Link`, ajoutez celle-ci plutôt que de les remplacer.

### Côté client : `fromHeaders()`

```php
/** @param list<string> $linkHeaders every Link header line of the response */
public static function fromHeaders(?string $deprecation, ?string $sunset, array $linkHeaders = []): ?self
```

Le lecteur est tolérant : **une valeur mal formée est ignorée, comme si elle était absente.**

- `Deprecation` doit être une sf-date : `@` et un entier, signé ou non, de quinze chiffres au plus. Les espaces autour
  et les paramètres de champ structuré (`@1688169599;foo=bar`) sont retirés. `true` (la forme des brouillons
  antérieurs à la RFC 9745), une date HTTP, un nombre nu, un décimal ou seize chiffres sont ignorés.
- `Sunset` est lu par `HttpDate::parse()`; tout le reste est ignoré.
- Si aucune date n'est utilisable, `fromHeaders()` retourne `null`, même quand un lien d'obsolescence est présent.
- **Des dates contradictoires gardent le retrait.** Un expéditeur qui a écrit son retrait avant son obsolescence a
  tout de même annoncé un retrait. Le retrait est la date qui brise l'appelant : la date d'obsolescence est donc
  écartée. Des dates égales ne sont pas contradictoires.
- Le lien est la première cible de relation `deprecation` parmi toutes les lignes `Link`, trouvée par
  `LinkHeader::firstWithRelation()`.

### Comparer avec maintenant

| Méthode | Retourne |
|---|---|
| `isDeprecatedAt(DateTimeInterface $now): bool` | `true` quand une date d'obsolescence existe et tombe au plus tard à `$now`. |
| `isSunsetAt(DateTimeInterface $now): bool` | `true` quand une date de retrait existe et tombe au plus tard à `$now`. |
| `noticeInDays(): ?int` | Les jours entiers entre les deux dates, arrondis vers le bas (`+90 days -1 second` donne `89`), ou `null` si l'une des dates manque. |

Une date absente n'est jamais atteinte.

Constantes : `DEPRECATION_HEADER = 'Deprecation'`, `SUNSET_HEADER = 'Sunset'`, `LINK_HEADER = 'Link'`,
`LINK_RELATION = 'deprecation'`.

## `HttpDate`

`Phil\ApiContract\Http\HttpDate` offre des méthodes statiques pour la date HTTP de la RFC 9110, section 5.6.7.

- `format(DateTimeInterface $moment): string` écrit l'IMF-fixdate préférée, en UTC, quel que soit le fuseau horaire de
  l'entrée : `Sun, 06 Nov 1994 08:49:37 GMT`.
- `parse(string $value): ?DateTimeImmutable` accepte les trois formes qu'un destinataire doit accepter : IMF-fixdate,
  RFC 850 (`Sunday, 06-Nov-94 08:49:37 GMT`) et asctime (`Sun Nov  6 08:49:37 1994`). Les espaces autour sont retirées
  et les suites d'espaces sont réduites. Pour tout le reste, elle retourne `null` plutôt que de lever une exception :
  une date ISO 8601, un fuseau autre que `GMT`, un jour impossible (`31 Feb`), un jour de la semaine qui ne correspond
  pas à la date, du texte à la fin. L'instant lu n'a pas de fraction de seconde.

Les dates RFC 850 portent une année à deux chiffres. PHP la lit dans la fenêtre de 1970 à 2069, ce qui donne la même
réponse que la règle des cinquante ans de la RFC 9110 pour toute date qu'une obsolescence peut porter.

## `LinkHeader`

`Phil\ApiContract\Deprecation\LinkHeader::firstWithRelation(array $headers, string $relation): ?string` implémente
juste assez de la RFC 8288 pour trouver la cible d'une relation :

- chaque élément de `$headers` est une ligne `Link`; une ligne contient des entrées `<uri>; param=value` séparées par
  des virgules;
- `rel` contient un ou plusieurs types de relation séparés par des espaces, comparés sans égard à la casse, entre
  guillemets ou non (`rel="help deprecation"`, `rel=deprecation` et `REL="Deprecation"` correspondent tous à
  `deprecation`);
- seul le premier paramètre `rel` d'une entrée compte;
- un `; rel=` à l'intérieur de la valeur entre guillemets d'un autre paramètre n'est pas un paramètre;
- une relation qui contient seulement le mot (`deprecation-notes`) ne correspond pas;
- une cible vide (`<>`) est ignorée;
- la première correspondance, en parcourant les lignes dans l'ordre, l'emporte; sans correspondance, le résultat est
  `null`.

## `ObservedDeprecations` (port)

L'endroit où un client garde les signaux d'obsolescence qu'il a reçus, point de terminaison par point de terminaison.
Il alimente trois lecteurs qu'écrit l'application : un journal qui avertit **une fois par point de terminaison et par
jour** plutôt qu'à chaque appel, un panneau du profileur, et une commande de vérification qu'exécute l'intégration
continue et qui échoue tant qu'un point de terminaison qu'elle appelle porte un retrait. Le contrat se trouve dans
[extension-points.md](extension-points.md#observeddeprecations).

Un parcours côté client, avec seulement les types de la bibliothèque :

```php
$signal = DeprecationSignal::fromHeaders(
    $response->getHeaders(false)['deprecation'][0] ?? null,
    $response->getHeaders(false)['sunset'][0] ?? null,
    $response->getHeaders(false)['link'] ?? [],
);

if (null !== $signal && $observed->record('GET /api/tenants', $signal, $clock->now())) {
    $logger->warning('The API endpoint {endpoint} is deprecated.', [
        'endpoint' => 'GET /api/tenants',
        'sunset' => $signal->sunsetAt?->format(DATE_ATOM),
        'link' => $signal->link,
    ]);
}
```

Un « point de terminaison » est un nom de route ou une méthode et un modèle de chemin, **jamais une URL qui contient des
identifiants**.

## Hors de la bibliothèque

Le décorateur de client HTTP qui appelle `fromHeaders()`, le journal, la commande de vérification et l'écouteur côté
serveur qui ajoute les en-têtes à une route obsolète. `phil/api-contract-bundle` apporte les quatre aux applications
Symfony.
