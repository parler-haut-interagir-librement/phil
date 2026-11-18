<!-- phil-dev source-sha256=687a1c6d24f0eea370fab4435520cba7fbcfa96e60b65fffdc2d8fbd240392b0 -->
# Compteurs

**Un compteur est la seule chose que la conception du bundle stocke.** Un compteur est un nombre dont la clé est un
site, un jour UTC, une métrique et une valeur d'une dimension déclarée. Aucun identifiant de compte, aucune adresse IP,
aucun témoin, aucun identifiant d'appareil, aucun instant plus fin que le jour, aucun agent utilisateur ni aucune URL
de référent ne fait jamais partie d'une clé ni n'est stocké à côté.

État : `SiteId`, `UtcDay` et `CounterKey` sont implémentés et testés ; le stockage est le port `CounterStore`, dont
l'adaptateur par défaut garde les compteurs dans une connexion Doctrine ([configuration.md](configuration.md#les-compteurs)).

## Le site : `SiteId`

`Phil\AnalyticsBundle\Domain\ValueObject\SiteId` est la référence d'un site mesuré, l'unité à laquelle appartient
chaque compteur. Elle est **opaque** : l'application en choisit la forme (un *slug*, un UUID).

```php
$site = SiteId::fromString('spring-con');
$site->value;          // 'spring-con'
(string) $site;        // 'spring-con'
$site->equals(SiteId::fromString('spring-con')); // true; comparison is case-sensitive
```

`fromString()` ne refuse que ce qui ne peut pas être une clé stable : une chaîne vide, des espaces autour, un caractère
de contrôle, ou plus de `SiteId::MAX_LENGTH` (255) octets. Un site n'est jamais une personne : rien dans cette
référence ne doit identifier un visiteur.

## Le jour : `UtcDay`

`Phil\AnalyticsBundle\Domain\ValueObject\UtcDay` est **toujours un jour UTC**, fixé une fois pour toutes à l'ingestion
d'après l'instant d'arrivée de la page vue, et le même pour tous les sites.

```php
UtcDay::of(new DateTimeImmutable('2026-12-31 23:30', new DateTimeZone('America/Toronto')))->value; // '2027-01-01'
$day = UtcDay::fromString('2028-02-29');  // read back a stored day, strict YYYY-MM-DD
$day->start();                            // DateTimeImmutable 2028-02-29T00:00:00+00:00
$day->next();                             // UtcDay '2028-03-01'
$day->isBefore(UtcDay::fromString('2028-03-01')); // true
$day->equals($other); $day->toString(); (string) $day;
```

Pourquoi : un compteur contient un nombre, pas les instants qui l'ont produit ; une limite de jour déplacée après coup
(un autre fuseau horaire) ne peut donc pas être respectée. Un agrégat n'est jamais recalculé dans un autre fuseau, et un
jour UTC n'a jamais 23 ni 25 heures. `fromString()` refuse tout ce qui n'est pas un vrai jour du calendrier sous la
forme `YYYY-MM-DD` (`2027-02-29`, `2026-13-01`, une partie horaire, un saut de ligne final).

## La clé : `CounterKey`

`Phil\AnalyticsBundle\Domain\ValueObject\CounterKey` dit ce que compte un compteur.

```php
$key = CounterKey::of(
    SiteId::fromString('spring-con'),
    UtcDay::of($now),
    'pageviews',   // metric
    'route',       // dimension
    'event',       // one value of that dimension
);
$key->site; $key->day; $key->metric; $key->dimension; $key->value;
$key->equals($other); // all five parts equal
```

Validation :

| Partie | Règle |
|---|---|
| `metric`, `dimension` | `CounterKey::NAME_PATTERN` : 1 à 64 caractères parmi `a-z`, `0-9`, `_`, `.`, `-`, commençant par une lettre |
| `value` | Non vide, au plus `CounterKey::MAX_VALUE_LENGTH` (128) octets, aucun caractère de contrôle (`\x00`–`\x1F`, `\x7F`) |

Deux valeurs réservées sont valides dans toute dimension :

| Constante | Valeur | Sens |
|---|---|---|
| `CounterKey::OTHER` | `(other)` | Tout ce qui n'est pas montré seul : à l'ingestion, un chemin qui ne correspond à aucune route déclarée ([declared-routes.md](declared-routes.md)) ; à la lecture, le groupe des compteurs sous le seuil des petits nombres ([small-number-suppression.md](small-number-suppression.md)) |
| `CounterKey::UNKNOWN` | `(unknown)` | Une dimension pour laquelle une page vue n'a pas de valeur, comme le pays d'une adresse que la recherche ne sait pas situer ([devices-and-countries.md](devices-and-countries.md)) |

### L'invariant que le type ne peut pas imposer

`CounterKey` vérifie la *forme* d'une clé ; **il ne peut pas distinguer un identifiant d'une valeur de dimension**.
Une dimension dont les valeurs sont ouvertes (un nom d'utilisateur, un numéro de commande, une URL complète) devient un
identifiant dès qu'une valeur correspond à une personne. **Garder chaque dimension à une liste fermée de valeurs est le
travail de l'application.** Le bundle aide là où il le peut : les routes sont ramenées à des noms déclarés, les sources
à six classes, les appareils à trois, les pays aux codes ISO — chacun une liste fermée.

### Une page vue, plusieurs compteurs

Une clé contient une valeur d'**une seule** dimension ; une page vue comptée sur plusieurs dimensions incrémente donc un
compteur par dimension. Les compteurs croisés (route × pays) ne font délibérément pas partie de la clé : chaque
dimension de plus multiplie le nombre de compteurs et rapetisse chacun vers la taille où il désigne une personne.

```php
foreach (['route' => $route, 'device' => $device->value, 'country' => $country->toString()] as $dimension => $value) {
    $store->increment(CounterKey::of($site, $day, 'pageviews', $dimension, $value));
}
```

Les noms de métriques et de dimensions sont le choix de l'application ; `MeasureNames` contient ceux qu'écrit le
collecteur du bundle.

## Pas de visiteurs uniques ; des visites à la place

**Le bundle ne compte aucun visiteur unique**, parce qu'en compter un demande un identifiant, même haché et salé.
**Une visite est une page vue dont la source n'est pas le site lui-même** (`ReferralKind::isVisit()`,
[sources-and-referrers.md](sources-and-referrers.md)). C'est moins exact que les visiteurs uniques, et c'est la seule
mesure qui s'en tient aux agrégats :

```php
if ($referral->isVisit()) {
    $store->increment(CounterKey::of($site, $day, 'visits', 'source', $referral->value));
}
```

## Stocker les compteurs : le port `CounterStore`

`Phil\AnalyticsBundle\Application\Contract\CounterStore` a un adaptateur par défaut, `DbalCounterStore`, et
l'application peut nommer le sien :

| Méthode | Contrat |
|---|---|
| `increment(CounterKey $key): void` | Une seule insertion-ou-mise-à-jour atomique (`INSERT ... ON CONFLICT DO UPDATE SET n = n + 1`, ou l'équivalent), pour que des pages vues simultanées ne perdent jamais un compte et qu'un stockage qui ne fait qu'incrémenter n'ait besoin d'aucun autre privilège |
| `counts(SiteId $site, string $metric, string $dimension, UtcDay $from, UtcDay $to): array<string, int>` | Les compteurs stockés d'une métrique et d'une dimension sur une plage de jours **inclusive**, additionnés par valeur de dimension. Sans masquage : l'appelant applique la règle des petits nombres à la lecture |
| `deleteSite(SiteId $site): void` | Supprime tous les compteurs d'un site, quand le site est retiré |

Voir [extension-points.md](extension-points.md#counterstore) pour un autre adaptateur.

### Les tables et leur collation

`DbalCounterStore` et `DbalCounterRollup` gardent les compteurs dans deux tables, `phil_analytics_counter` (une ligne
par site, jour UTC, métrique, dimension et valeur) et `phil_analytics_monthly_counter` (la même par mois). L'écouteur de
schéma les ajoute au schéma que l'ORM génère pour la connexion, pour que `doctrine:migrations:diff` écrive la migration
qui les crée ou les change.

**La clé d'un compteur se compare octet par octet.** Le site, la métrique, la dimension et sa valeur distinguent un
compteur d'un autre, et la métrique décide qui peut lire un compteur. Sur MySQL et MariaDB, les quatre colonnes sont en
`utf8mb4` avec la collation `utf8mb4_bin` : la collation par défaut d'un serveur, `utf8mb4_0900_ai_ci` sur MySQL 8,
ignore la casse et les accents, et lirait `OUTBOUND_LINK` comme `outbound_link`, `Acme` comme `acme` et `rosé` comme
`rose`. Les collations par défaut de PostgreSQL sont déterministes et SQLite compare les octets : les deux distinguent
déjà ces valeurs, et leurs colonnes gardent la valeur par défaut.

Le côté lecture ne se fie jamais à la seule collation : `CounterReader` et `SharedView::mayShow()` n'acceptent une
métrique que sous sa forme canonique, `MeasureNames::isMetric()`, et une dimension que sous un nom de
`CounterKey::NAME_PATTERN` ([sharing-reports-alerts.md](sharing-reports-alerts.md#tableaux-de-bord-et-plusieurs-sites)).

### Mise à niveau depuis 0.1

Les tables d'une installation 0.1 sur MySQL ou MariaDB gardaient la collation de la connexion. Générez la migration et
exécutez-la :

```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

La migration change `site`, `metric`, `dimension` et `dimension_value` des deux tables en `CHARACTER SET utf8mb4 COLLATE
utf8mb4_bin`. Le bundle a toujours écrit les métriques et les dimensions en minuscules : leurs lignes ne changent donc
pas. Les compteurs qu'une collation insensible à la casse avait fusionnés — les pages vues des sites `Acme` et `acme`,
par exemple, ou les termes de recherche `Rose` et `rose` — restent fusionnés sous la valeur écrite en premier : le passé
ne peut plus être scindé, et les nouveaux comptes sont gardés distincts à partir de la migration. Sur PostgreSQL et
SQLite, le diff est vide.
