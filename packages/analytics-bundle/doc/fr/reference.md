<!-- phil-dev source-sha256=8791fa674557ec682c7ef0efc633229efe5089b8274181687170be4e1081cf99 -->
# Référence de l'API

Chaque type public du bundle, par couche, avec son rôle et son état. Les espaces de noms sont relatifs à
`Phil\AnalyticsBundle`. Tous les objets-valeurs sont `final readonly`, construits par des constructeurs nommés, et
lancent `Phil\DddBundle\Domain\Exception\InvalidValue` sur une valeur invalide.

## Bundle

| Type | Rôle | État |
|---|---|---|
| `PhilAnalyticsBundle` | La classe du bundle ; alias d'extension `phil_analytics` | Implémenté et testé ([configuration.md](configuration.md)) |

## Domaine — objets-valeurs (`Domain\ValueObject`)

| Type | Rôle | État |
|---|---|---|
| `SiteId` | Référence opaque d'un site mesuré ; `fromString()`, `equals()`, `toString()` ; `MAX_LENGTH` = 255 | Implémenté et testé ([counters.md](counters.md)) |
| `UtcDay` | Le jour UTC d'un compteur ; `of()`, `fromString()`, `start()`, `next()`, `isBefore()`, `equals()`, `toString()` | Implémenté et testé ([counters.md](counters.md)) |
| `CounterKey` | Site, jour, métrique, dimension, valeur ; `of()`, `equals()` ; `OTHER`, `UNKNOWN`, `NAME_PATTERN`, `MAX_VALUE_LENGTH` | Implémenté et testé ([counters.md](counters.md)) |
| `DeclaredRoute` | Un nom de route et un motif de chemin ; `of()`, `matches()`, `specificity()`, `length()`, `split()`, `hasSameShapeAs()` | Implémenté et testé ([declared-routes.md](declared-routes.md)) |
| `DeclaredRoutes` | La liste fermée des routes d'un site ; `of()`, `none()`, `normalize()` | Implémenté et testé ([declared-routes.md](declared-routes.md)) |
| `Origin` | Une origine http(s) normalisée ; `fromString()`, `ofUrl()`, `ofRequest()`, `equals()`, `toString()` | Implémenté et testé ([collection-and-admission.md](collection-and-admission.md)) |
| `CollectionSite` | Les origines, routes et état d'un site, la forme de la projection de collecte ; `of()`, `declares()` | Implémenté et testé ([collection-and-admission.md](collection-and-admission.md)) |
| `HostList` | Des noms d'hôtes auxquels on compare l'hôte d'un référent, avec l'étiquette de suffixe public `{}` ; `of()`, `empty()`, `matches()` | Implémenté et testé ([sources-and-referrers.md](sources-and-referrers.md)) |
| `CountryCode` | Un code ISO 3166-1 alpha-2 ou inconnu ; `fromString()`, `unknown()`, `isKnown()`, `equals()`, `toString()` | Implémenté et testé ([devices-and-countries.md](devices-and-countries.md)) |
| `PrivacySignals` | Do Not Track et Global Privacy Control ; `fromHeaders()`, `none()`, `optOut()` | Implémenté et testé ([privacy-signals.md](privacy-signals.md)) |
| `ClientDigest` | L'empreinte que garde un limiteur, `<salt id>:<hex>` ; `fromString()`, `equals()`, `toString()` | Implémenté et testé ([rate-limiting.md](rate-limiting.md)) |
| `UtcMonth` | Le mois d'un compteur cumulé ; `of()`, `fromString()`, `minus()`, `firstDay()`, `toString()` | Implémenté et testé ([retention.md](retention.md)) |
| `SmallNumberThreshold` | Le compte sous lequel un compteur n'est jamais montré seul ; `of()` (au moins 2), `isMetBy()` | Implémenté et testé ([small-number-suppression.md](small-number-suppression.md)) |

## Domaine — règles et énumérations

| Type | Rôle | État |
|---|---|---|
| `Domain\Collection\SiteStatus` | `Active`, `Suspended`, `Retired` ; `collects()` | Implémenté et testé |
| `Domain\Collection\Admission` | Ce que fait une page vue ; `advancesAudience()`, `advancesRobotVerdictCounter()`, `isRefusal()` | Implémenté et testé |
| `Domain\Collection\AdmissionRule` | `decide(SiteStatus, PrivacySignals, bool $declaredCrawler, ReferralKind, Verdict): Admission`, dans un ordre fixe | Implémenté et testé, sur le `Verdict` de `phil/bot-detection-bundle` |
| `Domain\Collection\ClientDigester` | `digestOf(string $ip): ClientDigest`, un `Pipeline` de `phil/ip-anonymizer` sous un `SaltProvider` | Implémenté et testé ([rate-limiting.md](rate-limiting.md)) |
| `Domain\Source\ReferralKind` | `Direct`, `Search`, `Social`, `Website`, `Internal`, `Spam` ; `isVisit()` | Implémenté et testé |
| `Domain\Source\ReferrerClassifier` | `classify(?string $referer, iterable<Origin> $ownOrigins): ReferralKind` sur trois `HostList` | Implémenté et testé |
| `Domain\Device\DeviceClass` | `Desktop`, `Tablet`, `Mobile` ; `fromUserAgent()` | Implémenté et testé |
| `Domain\Country\GeoDatabaseFreshness` | `maxAgeDays()`, `isUsable(DateTimeImmutable $builtAt, DateTimeImmutable $now)` | Implémenté et testé |
| `Domain\Reading\SmallNumberSuppression` | `apply(array<string, int>): SuppressedCounts`, à la lecture | Implémenté et testé |
| `Domain\Reading\SuppressedCounts` | `shown`, `others`, `hidden` | Implémenté et testé |
| `Domain\Reading\LinkCountReader` | `administrator()`, `author()`, `maySee()`, `counts()` | Implémenté et testé ([measures.md](measures.md)) |
| `Domain\Reading\ReaderScope` | `everySite()`, `ofSites()`, `mayRead()`, `coversEverySite()` ; `MAX_SITES` = 1000 | Implémenté et testé ([sharing-reports-alerts.md](sharing-reports-alerts.md#tableaux-de-bord-et-plusieurs-sites)) |

## Domaine — mesures (`Domain\Measure`)

Tous implémentés et testés ([measures.md](measures.md)).

| Type | Rôle |
|---|---|
| `MeasurePlan` | Ce qu'un site mesure au-delà des pages vues, ses événements du serveur compris ; `of()` ; `MAX_SERVER_EVENT_LENGTH` = 50 |
| `MeasureEvent` | La liste fermée des événements ; `isClick()` |
| `MeasureNames` | Les métriques qu'écrit le bundle (dont `SERVER_EVENTS`), `OPEN_DIMENSIONS`, `PRIVATE_METRICS` |
| `ClickTracking` | `Off`, `Aggregate` ; `counts()` |
| `ClosedList` | Des valeurs déclarées ; `of()`, `empty()`, `downloads()`, `has()`, `toArray()` |
| `DeclaredDimension` | Une dimension du site et ses valeurs ; `of()` |
| `Goal` | `onRoute()`, `onEvent()`, `isReachedByRoute()`, `isReachedByEvent()` |
| `ConversionRate` | Les objectifs par visite en points de base, ou `null` sur de petits nombres ; `of()` |
| `RouteTransition` | `valueOf()` : la valeur de compteur d'une paire de routes |
| `SearchTerm` | `valueOf()` : un terme normalisé, ou `null` |
| `OutboundHost` | `valueOf()` : un hôte, ou `null` |
| `DeclaredOutboundLink` | La référence d'un lien et son auteur ; `of()` |
| `ScrollDepth`, `ActiveTime` | Les tranches de profondeur de lecture et de temps actif |

## Domaine — rétention, partage, rapports, alertes

Tous implémentés et testés ([retention.md](retention.md), [sharing-reports-alerts.md](sharing-reports-alerts.md)).

| Type | Rôle |
|---|---|
| `Domain\Retention\CounterRetention` | `firstDailyDay(UtcDay $today)` ; 13 mois par défaut |
| `Domain\Sharing\ShareToken` | `generate()`, `fromString()`, `digest()` |
| `Domain\Sharing\SharedView` | `link()`, `publicBoard()`, `revoked()`, `isOpenAt()`, `mayShow()` |
| `Domain\Report\ReportCadence` | `Weekly`, `Monthly` |
| `Domain\Report\ReportPeriod` | `lastCompleteBefore(UtcDay, ReportCadence)` |
| `Domain\Report\ReportSubscription` | L'identifiant, le site, la cadence et le destinataire opaque d'un abonnement |
| `Domain\Alert\TrafficAlertRule` | `evaluate(SiteId, UtcDay, int, list<int>): ?TrafficAlert` |
| `Domain\Alert\TrafficAlert`, `TrafficAlertKind` | Un jour qui se démarque : `Spike` ou `Dip` |

## Application — services

Tous implémentés et testés.

| Type | Rôle |
|---|---|
| `Application\Measure\MeasureCounters` | `ofPageView()`, `ofEvent()` : les compteurs qu'un envoi fait avancer, lus dans un corps non fiable ([measures.md](measures.md)) |
| `Application\Collection\Collector` | `collect(CollectionRequest): CollectionOutcome`, l'ordre de la collecte ; `MAX_BODY_BYTES` = 4096 ([collection-and-admission.md](collection-and-admission.md#le-point-de-collecte)) |
| `Application\Collection\CollectionRequest` | Les en-têtes, l'adresse du client, l'agent utilisateur et le corps que lit la collecte |
| `Application\Collection\CollectionOutcome` | `Counted`, `NotCounted`, `Refused`, `OverBudget`, `TooLarge` ; `httpStatus()` |
| `Application\Retention\CounterRetentionRun` | `runFor(UtcDay)` : le cumul des jours qui ont passé leur rétention ([scheduled-jobs.md](scheduled-jobs.md#rétention)) |
| `Application\Report\DueReports` | `sendFor(UtcDay)`, `cadencesDueOn(UtcDay)` : les rapports dus un jour donné ([scheduled-jobs.md](scheduled-jobs.md#rapports)) |
| `Application\Measure\ServerEventCounter` | `count(SiteId, string $event, array $dimensions): bool` : un événement que compte le serveur de l'application ([measures.md](measures.md#événements-comptés-par-le-serveur)) |
| `Application\Reading\CounterReader` | `read()`, `readAcross()` : ce que lit un tableau de bord, dans une `ReaderScope` ; `MAX_DAYS` = 400 ([sharing-reports-alerts.md](sharing-reports-alerts.md#tableaux-de-bord-et-plusieurs-sites)) |
| `Application\Alert\TrafficAlertRun` | `checkFor(UtcDay)` : les visites de la veille comparées au jour habituel ([scheduled-jobs.md](scheduled-jobs.md#alertes-de-trafic)) |
| `Application\Message\ApplyCounterRetention`, `SendDueReports`, `CheckBrokenFlows`, `CheckTrafficAlerts` | Les messages quotidiens, sans contenu ([scheduled-jobs.md](scheduled-jobs.md)) |

## Application — contrats (`Application\Contract`)

| Type | Rôle | État |
|---|---|---|
| `CounterStore` | `increment()`, `counts()`, `deleteSite()` | Port, par défaut `DbalCounterStore` ([extension-points.md](extension-points.md#counterstore)) |
| `CollectionProjection` | `siteDeclaring(Origin): ?CollectionSite`, `site(SiteId): ?CollectionSite` | Port, par défaut `ConfiguredCollectionProjection` ([extension-points.md](extension-points.md#collectionprojection)) |
| `CollectionRateLimiter` | `consume(SiteId, ClientDigest): bool`, fermé en cas d'échec | Port, par défaut `SymfonyCollectionRateLimiter` ([extension-points.md](extension-points.md#collectionratelimiter)) |
| `CountryResolver` | `countryOf(string $ip): CountryCode` | Port, par défaut `UnknownCountryResolver` ([extension-points.md](extension-points.md#countryresolver)) |
| `CrawlerDetector` | `isDeclaredCrawler(string $userAgent): bool` | Port, par défaut `UserAgentCrawlerDetector` ([extension-points.md](extension-points.md#crawlerdetector)) |
| `OutboundLinkDirectory` | `find(SiteId, string): ?DeclaredOutboundLink` | Port, par défaut `NoOutboundLinks` ([extension-points.md](extension-points.md#outboundlinkdirectory)) |
| `CounterRollup` | `rollUpBefore()`, `monthlyCounts()` | Port, par défaut `DbalCounterRollup` ([extension-points.md](extension-points.md#counterrollup)) |
| `SharedViews` | `save()`, `linkOfTokenDigest()`, `publicBoardOf()` | Port seulement ([extension-points.md](extension-points.md#sharedviews)) |
| `ReportSubscriptions`, `ReportSender`, `TrafficAlertNotifier` | Les rapports et les alertes | Port seulement ([extension-points.md](extension-points.md#reportsubscriptions-reportsender-trafficalertnotifier)) |

## Repris de `phil/bot-detection-bundle`

| Type | Rôle | État |
|---|---|---|
| `Phil\BotDetectionBundle\Domain\Verdict\Verdict` | Le verdict `Human`, `Robot` ou `Unknown` du navigateur, lu strictement par `fromSubmitted()` | Implémenté dans le bundle voisin |
| `Phil\BotDetectionBundle\Domain\Counter\RobotVerdictCounter` | Le compteur agrégé des verdicts robot, `increment()` sans argument | Port, pour une application qui tient aussi cet agrégat ; `Collector` compte les verdicts robot par site dans le stockage des compteurs |

## Infrastructure — les adaptateurs par défaut et la configuration

Tous implémentés et testés ([configuration.md](configuration.md#les-adaptateurs)).

| Type | Rôle |
|---|---|
| `Infrastructure\Doctrine\DbalCounterStore` | `CounterStore` dans `phil_analytics_counter` : un `UPDATE`, sinon un `INSERT`, repris en `UPDATE` sur une insertion simultanée ; `integers()` |
| `Infrastructure\Doctrine\DbalCounterRollup` | `CounterRollup` vers `phil_analytics_monthly_counter`, en une seule transaction |
| `Infrastructure\Doctrine\CounterTables` | `configure(Schema)` : les deux tables ; `DAILY`, `MONTHLY` |
| `Infrastructure\Doctrine\CounterSchemaListener` | Ajoute les deux tables au schéma que génère l'ORM |
| `Infrastructure\Salt\CacheSaltStore` | `ExpiringSaltStore` de `phil/ip-anonymizer` dans une réserve PSR-6, sous un verrou facultatif |
| `Infrastructure\RateLimiter\SymfonyCollectionRateLimiter` | `CollectionRateLimiter` sur une `RateLimiterFactoryInterface`, fermé en cas d'échec |
| `Infrastructure\Collection\ConfiguredCollectionProjection` | `CollectionProjection` des sites sous `phil_analytics.sites` |
| `Infrastructure\Collection\UserAgentCrawlerDetector` | `CrawlerDetector` des agents utilisateurs qui le disent |
| `Infrastructure\Collection\UnknownCountryResolver` | `CountryResolver` qui répond « inconnu » |
| `Infrastructure\Collection\NoOutboundLinks` | `OutboundLinkDirectory` sans lien déclaré |
| `Infrastructure\Http\CollectController` | Le point de collecte, route `phil_analytics_collect` |
| `Infrastructure\Messenger\ApplyCounterRetentionHandler`, `SendDueReportsHandler`, `CheckBrokenFlowsHandler`, `CheckTrafficAlertsHandler` | Les *handlers* des messages quotidiens |
| `Infrastructure\Scheduler\AnalyticsSchedule` | L'horaire `phil_analytics` ; `NAME` |
| `Infrastructure\BrokenFlow\GoalCompletionHistory` | `CompletionHistory` de `phil/broken-flow-alerts`, d'après les compteurs d'objectifs ; `flows()` |
| `Infrastructure\DependencyInjection\AnalyticsConfiguration`, `AnalyticsServices`, `CounterWiring` | L'arbre de `phil_analytics` et les services qu'il configure |

## Laissé à l'application

| Partie | Où |
|---|---|
| Les pages des tableaux de bord, leurs règles d'accès et leurs graphiques | Les contrôleurs et gabarits de l'application, sur `CounterReader` et une `ReaderScope` |
