<!-- phil-dev source-sha256=17e135b449fadcf1717cf4341bc8bae263a332f056c05a396ca949629398ded3 -->
# Points d'extension

Le bundle décide ce que décide la conception, et laisse à l'application ce que chaque application décide pour
elle-même, par des **contrats** : des interfaces PHP sous `Phil\AnalyticsBundle\Application\Contract`, plus une
interface reprise de `phil/bot-detection-bundle`. Le bundle configure un adaptateur par défaut pour la plupart ;
l'application en remplace un en nommant son propre service dans la configuration, et implémente les autres elle-même
([configuration.md](configuration.md#les-adaptateurs)).

| Contrat | Méthode(s) | Sert à | Adaptateur par défaut, clé de configuration |
|---|---|---|---|
| `CounterStore` | `increment()`, `counts()`, `deleteSite()` | Où vivent les compteurs | `DbalCounterStore`, `counters.store` |
| `CollectionProjection` | `siteDeclaring()`, `site()` | À quel site appartient une origine, ses routes, son état et son plan | `ConfiguredCollectionProjection`, `collection.projection` |
| `CollectionRateLimiter` | `consume()` | Borner la collecte par site et par empreinte | `SymfonyCollectionRateLimiter`, `rate_limiter.limiter` |
| `CountryResolver` | `countryOf()` | Le pays d'une adresse, en mémoire | `UnknownCountryResolver`, `collection.country_resolver` |
| `CrawlerDetector` | `isDeclaredCrawler()` | Les robots d'indexation qui se déclarent | `UserAgentCrawlerDetector`, `collection.crawler_detector` |
| `OutboundLinkDirectory` | `find()` | Les liens écrits dans les pages des auteurs | `NoOutboundLinks`, `collection.outbound_links` |
| `CounterRollup` | `rollUpBefore()`, `monthlyCounts()` | Les compteurs quotidiens qui ont passé leur rétention, et les totaux mensuels | `DbalCounterRollup`, `counters.rollup` |
| `ExpiringSaltStore` (de `phil/ip-anonymizer`) | `claim()` | Le sel du jour, partagé par les serveurs | `CacheSaltStore`, `salt.store` |
| `SharedViews` | `save()`, `linkOfTokenDigest()`, `publicBoardOf()` | Les liens de partage et le tableau public | aucun |
| `ReportSubscriptions` | `ofCadence()` | Qui reçoit quel rapport | aucun, `reports.subscriptions` |
| `ReportSender` | `send()` | Envoyer un rapport | aucun, `reports.sender` |
| `TrafficAlertNotifier` | `notify()` | Prévenir quelqu'un d'un pic ou d'un creux | aucun, `traffic_alerts.notifier` |
| `RobotVerdictCounter` (de `phil/bot-detection-bundle`) | `increment()` | Le compte agrégé des verdicts robot | aucun ; `Collector` compte par site dans le stockage des compteurs |

Un remplaçant est nommé dans la configuration, et le bundle fait du contrat un alias de ce service :

```yaml
phil_analytics:
    collection:
        country_resolver: App\Analytics\GeoIpCountryResolver
```

Les exemples ci-dessous sont des esquisses de remplaçants dans le code de l'application. Les bibliothèques qu'ils
utilisent (Doctrine DBAL, Symfony Rate Limiter, GeoIP2, Crawler-Detect) sont des dépendances de l'application, pas du
bundle.

## `CounterStore`

```php
namespace Phil\AnalyticsBundle\Application\Contract;

interface CounterStore
{
    public function increment(CounterKey $key): void;

    /** @return array<string, int> */
    public function counts(SiteId $site, string $metric, string $dimension, UtcDay $from, UtcDay $to): array;

    public function deleteSite(SiteId $site): void;
}
```

| Méthode | Contrat |
|---|---|
| `increment()` | **Une seule insertion-ou-mise-à-jour atomique**, pour que des pages vues simultanées ne perdent jamais un compte. Stocke un nombre par clé et rien de ce qui l'a produit : aucun instant plus fin que le jour, aucune adresse, aucun agent utilisateur, aucune URL de référent |
| `counts()` | Les compteurs d'une métrique et d'une dimension, sur une plage de jours **inclusive**, **additionnés par valeur de dimension**, indexés par valeur. Sans masquage : l'appelant applique `SmallNumberSuppression` |
| `deleteSite()` | Supprime tous les compteurs du site, quand il est retiré |

```php
final readonly class DbalCounterStore implements CounterStore
{
    public function __construct(private Connection $db) {}

    public function increment(CounterKey $key): void
    {
        $this->db->executeStatement(
            'INSERT INTO analytics_counter (site, day, metric, dimension, value, n)
             VALUES (?, ?, ?, ?, ?, 1)
             ON CONFLICT (site, day, metric, dimension, value) DO UPDATE SET n = analytics_counter.n + 1',
            [$key->site->value, $key->day->value, $key->metric, $key->dimension, $key->value],
        );
    }

    public function counts(SiteId $site, string $metric, string $dimension, UtcDay $from, UtcDay $to): array
    {
        return array_map('intval', $this->db->fetchAllKeyValue(
            'SELECT value, SUM(n) FROM analytics_counter
             WHERE site = ? AND metric = ? AND dimension = ? AND day BETWEEN ? AND ?
             GROUP BY value',
            [$site->value, $metric, $dimension, $from->value, $to->value],
        ));
    }

    public function deleteSite(SiteId $site): void
    {
        $this->db->executeStatement('DELETE FROM analytics_counter WHERE site = ?', [$site->value]);
    }
}
```

Le processus de collecte n'a besoin que de `INSERT`, `UPDATE` et `SELECT` sur la table des compteurs (l'insertion ou
mise à jour lit la valeur qu'elle incrémente) ; la suppression et les tableaux de bord tournent ailleurs, avec leur
propre rôle.

## `CollectionProjection`

```php
interface CollectionProjection
{
    /** The site that declares this origin, or null: the request is then refused without a write. */
    public function siteDeclaring(Origin $origin): ?CollectionSite;

    /** The site of this reference, or null: what the server counts is checked against the same declaration. */
    public function site(SiteId $site): ?CollectionSite;
}
```

Invariants :

- **La collecte lit cette projection, jamais l'application qui la tient à jour.** L'application l'écrit quand la
  déclaration d'un site change et quand un site est suspendu, rétabli ou retiré ; la façon dont elle l'écrit ne fait
  pas partie du port.
- Elle ne contient aucune information personnelle : des origines, des routes, un état (la forme de `CollectionSite`).
- Une origine appartient à un site au plus. Comparez avec `Origin::equals()` ou par le `toString()` normalisé.
- `site()` rend la même `CollectionSite` que `siteDeclaring()` pour n'importe laquelle de ses origines : `ServerEventCounter`
  la lit, pour qu'un événement compté par le serveur suive la déclaration et l'état que suit une page vue.

```php
final readonly class DbalCollectionProjection implements CollectionProjection
{
    public function __construct(private Connection $db) {}

    public function siteDeclaring(Origin $origin): ?CollectionSite
    {
        // analytics_collection_site(site, routes_json, status); analytics_collection_origin(origin, site)
        $row = $this->db->fetchAssociative(
            'SELECT s.site, s.routes_json, s.status
             FROM analytics_collection_origin o JOIN analytics_collection_site s ON s.site = o.site
             WHERE o.origin = ?',
            [$origin->toString()],
        );

        if (false === $row) {
            return null;
        }

        $origins = $this->db->fetchFirstColumn(
            'SELECT origin FROM analytics_collection_origin WHERE site = ?',
            [$row['site']],
        );

        return CollectionSite::of(
            SiteId::fromString($row['site']),
            array_map(Origin::fromString(...), $origins),
            DeclaredRoutes::of(array_map(
                static fn (array $route): DeclaredRoute => DeclaredRoute::of($route['name'], $route['pattern']),
                json_decode($row['routes_json'], true, flags: \JSON_THROW_ON_ERROR),
            )),
            SiteStatus::from($row['status']),
        );
    }

    public function site(SiteId $site): ?CollectionSite
    {
        $origin = $this->db->fetchOne('SELECT origin FROM analytics_collection_origin WHERE site = ? LIMIT 1', [$site->value]);

        return false === $origin ? null : $this->siteDeclaring(Origin::fromString($origin));
    }
}
```

La disposition du stockage vous appartient. Ce qui compte, c'est que toutes les origines déclarées du site soient
retournées, pour que le classeur reconnaisse les référents internes, et que les origines soient stockées sous leur forme
normalisée (`Origin::toString()`).

## `CollectionRateLimiter`

```php
interface CollectionRateLimiter
{
    public function consume(SiteId $site, ClientDigest $digest): bool;
}
```

Invariants : **fermé en cas d'échec** (retourne `false` ou lance une exception quand son stockage ne peut pas répondre) ;
garde l'empreinte, jamais l'adresse ; une durée de vie qui ne dépasse pas la fenêtre du budget ; un stockage non
persistant ; un budget calibré sur un groupe derrière une seule adresse. Voir [rate-limiting.md](rate-limiting.md).

```php
final readonly class SymfonyCollectionRateLimiter implements CollectionRateLimiter
{
    public function __construct(private RateLimiterFactoryInterface $collectionLimiter) {}

    public function consume(SiteId $site, ClientDigest $digest): bool
    {
        try {
            return $this->collectionLimiter->create($site->value.'|'.$digest->toString())->consume()->isAccepted();
        } catch (\Throwable) {
            return false; // fail closed
        }
    }
}
```

## `CountryResolver`

```php
interface CountryResolver
{
    public function countryOf(#[\SensitiveParameter] string $ip): CountryCode;
}
```

Invariants : **l'adresse sert puis est oubliée** — ni journalisée ni stockée ; répond `CountryCode::unknown()` quand il
ne peut pas savoir, y compris quand sa base dépasse l'âge que permet sa licence (`GeoDatabaseFreshness`) ; ne lance
jamais d'exception pour une adresse qu'il ne sait pas situer.

```php
final readonly class GeoIpCountryResolver implements CountryResolver
{
    public function __construct(
        private \GeoIp2\Database\Reader $reader,
        private GeoDatabaseFreshness $freshness,
        private ClockInterface $clock,
    ) {}

    public function countryOf(#[\SensitiveParameter] string $ip): CountryCode
    {
        $builtAt = new \DateTimeImmutable('@'.$this->reader->metadata()->buildEpoch);

        if (!$this->freshness->isUsable($builtAt, $this->clock->now())) {
            return CountryCode::unknown();
        }

        try {
            $code = $this->reader->country($ip)->country->isoCode;
        } catch (\Throwable) {
            return CountryCode::unknown();
        }

        return null === $code ? CountryCode::unknown() : CountryCode::fromString($code);
    }
}
```

## `CrawlerDetector`

```php
interface CrawlerDetector
{
    public function isDeclaredCrawler(string $userAgent): bool;
}
```

Seuls les robots d'indexation qui se **déclarent** concernent ce port ; les autres sont laissés au verdict robot du
navigateur et au limiteur de débit. Une liste de robots sous licence MIT comme `jaybizzle/crawler-detect` est la source
prévue.

```php
final readonly class CrawlerDetectAdapter implements CrawlerDetector
{
    public function isDeclaredCrawler(string $userAgent): bool
    {
        return new \Jaybizzle\CrawlerDetect\CrawlerDetect()->isCrawler($userAgent);
    }
}
```

## `OutboundLinkDirectory`

```php
interface OutboundLinkDirectory
{
    public function find(SiteId $site, string $reference): ?DeclaredOutboundLink;
}
```

Invariants : ne répond que pour des liens que l'application a elle-même écrits dans une page ; `null` pour tout le
reste, pour qu'une référence forgée ne compte que l'hôte. La référence est opaque et c'est ce que la page met dans
`data-phil-analytics-link` ; n'en faites jamais l'URL, qui peut contenir un jeton. Voir
[measures.md](measures.md#liens-sortants).

## `CounterRollup`

```php
interface CounterRollup
{
    public function rollUpBefore(UtcDay $firstDailyDay, SmallNumberThreshold $threshold): void;

    /** @return array<string, int> */
    public function monthlyCounts(SiteId $site, string $metric, string $dimension, UtcMonth $from, UtcMonth $to): array;
}
```

Invariants : `rollUpBefore()` ajoute les jours avant la borne à leurs mois et les supprime **en une seule transaction**,
pour qu'un compte ne soit jamais dans les deux ni dans aucun ; le lancer deux fois ne change rien ; le total mensuel
d'une dimension de `MeasureNames::OPEN_DIMENSIONS` sous le seuil n'est pas gardé. Voir [retention.md](retention.md).

## `SharedViews`

```php
interface SharedViews
{
    public function save(SharedView $view): void;

    public function linkOfTokenDigest(string $tokenDigest): ?SharedView;

    public function publicBoardOf(SiteId $site): ?SharedView;
}
```

Invariants : stocke l'empreinte du jeton, jamais le jeton ; une recherche se fait par empreinte seulement. Voir
[sharing-reports-alerts.md](sharing-reports-alerts.md).

## `ReportSubscriptions`, `ReportSender`, `TrafficAlertNotifier`

```php
interface ReportSubscriptions
{
    /** @return iterable<ReportSubscription> */
    public function ofCadence(ReportCadence $cadence): iterable;
}

interface ReportSender
{
    public function send(ReportSubscription $subscription, ReportPeriod $period): void;
}

interface TrafficAlertNotifier
{
    public function notify(TrafficAlert $alert): void;
}
```

Invariants : un rapport lit les compteurs par `SmallNumberSuppression` et laisse de côté
`MeasureNames::PRIVATE_METRICS` ; un message mis en file pour un expéditeur contient l'identifiant de l'abonnement et la
période, et le destinataire est résolu à l'envoi du rapport.

## Le compteur des verdicts robot

`Phil\BotDetectionBundle\Domain\Counter\RobotVerdictCounter` est repris plutôt que redéclaré :

```php
interface RobotVerdictCounter
{
    public function increment(): void;
}
```

Son `increment()` ne prend **aucun argument**, pour que rien d'identifiant ne puisse l'atteindre — pas même le site.
`Collector` ne l'utilise pas : il compte les verdicts robot par site et par jour UTC dans le stockage des compteurs, sous
`robot_verdicts`. Une application qui tient aussi cet agrégat configure **un compteur par site**, jamais un argument de
site, et ne l'incrémente que quand `Admission::advancesRobotVerdictCounter()` est vrai. Rapportez son compte hors des
agrégats d'analytique (un tableau de bord d'alerte, une jauge de métriques) : un compte qui tombe à zéro pour un site qui
en a d'habitude, c'est ainsi que se voit un détecteur silencieux. Voir la documentation du bundle de détection des robots
pour les adaptateurs.

## Tester des adaptateurs

Voir [testing.md](testing.md#tester-vos-adaptateurs).
