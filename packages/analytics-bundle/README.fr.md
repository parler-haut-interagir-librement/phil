<!-- phil-dev source-sha256=301e07c5c69d4b9f099ac76f5b82a6ef92060b85fbe8f95a493eff4d6064a713 -->
# phil/analytics-bundle

**Les visites de vos sites comptées en agrégats quotidiens, jamais liées à une personne.**

phil/analytics-bundle tient un compteur par site, par jour UTC, par métrique et par valeur d'une dimension déclarée.
Autour de ce compteur, il décide à quel site appartient une requête de collecte et sous quelle route déclarée un
chemin est compté. Il classe aussi le référent, l'appareil et le pays, et respecte Do Not Track et Global Privacy
Control. Une empreinte de client à clé sert à la limitation de débit, et les petits nombres sont masqués à la lecture
des compteurs. Au-delà des pages vues, il compte les objectifs, les clics, les téléchargements, les pages introuvables,
les formulaires, les liens sortants, les paires de routes, la profondeur de lecture et les termes de recherche,
toujours sous forme de compteurs qui se suffisent à eux-mêmes. Il ne garde aucun identifiant de compte, aucune adresse
IP, aucun témoin et aucun identifiant d'appareil, et ne compte aucun visiteur unique.

Il s'adresse aux applications qui servent un ou plusieurs sites et veulent montrer à leurs responsables ce qui est lu,
sans suivre les visiteurs. Mesurer en agrégats retire les données personnelles que contiendrait un journal des
visiteurs. Le bundle n'installe aucun produit d'analytique, aucune bibliothèque d'analyse des référents et aucune
bibliothèque de détection des appareils.

Une page vue reprend le verdict robot ou humain de `phil/bot-detection-bundle`, pour que les robots restent hors des
compteurs d'audience. Le script de collecte est un contrôleur Stimulus qui ne pose aucun témoin et ne stocke rien dans
le navigateur.

Enregistré et configuré, le bundle collecte : un point de collecte, des compteurs dans deux tables d'une connexion
Doctrine, une empreinte de client sous un sel tiré chaque jour UTC puis oublié, un budget Symfony Rate Limiter par site
et par empreinte, les événements que compte le serveur de l'application, la lecture des tableaux de bord sur un site ou
plusieurs, et une fois par jour la rétention des compteurs, les rapports dus, la vérification des objectifs qui ne sont
plus atteints et l'alerte sur un site dont le trafic fait un pic ou un creux. Chacun est un adaptateur derrière un contrat, et l'application remplace n'importe lequel en
nommant son propre service dans la configuration : son propre stockage, sa propre projection des sites, une recherche
de pays, une liste de robots d'indexation.

## État

| Partie | État |
|---|---|
| Compteurs, jours UTC, sites (`CounterKey`, `UtcDay`, `SiteId`) | Implémenté et testé |
| Routes déclarées (`DeclaredRoute`, `DeclaredRoutes`) | Implémenté et testé |
| Origines et forme de la projection de collecte (`Origin`, `CollectionSite`, `SiteStatus`) | Implémenté et testé |
| Admission d'une page vue (`AdmissionRule`, `Admission`) | Implémenté et testé, sur le `Verdict` de `phil/bot-detection-bundle` |
| Classement des référents (`HostList`, `ReferrerClassifier`, `ReferralKind`) | Implémenté et testé |
| Classes d'appareils et pays (`DeviceClass`, `CountryCode`, `GeoDatabaseFreshness`) | Implémenté et testé |
| Signaux de confidentialité (`PrivacySignals`) | Implémenté et testé |
| Empreinte de client à clé (`ClientDigester`, `ClientDigest`), sur `phil/ip-anonymizer` | Implémenté et testé |
| Masquage des petits nombres (`SmallNumberThreshold`, `SmallNumberSuppression`, `SuppressedCounts`) | Implémenté et testé |
| Ce qu'un site mesure (`MeasurePlan`, `ClosedList`, `Goal`, `DeclaredDimension`, `MeasureCounters`) | Implémenté et testé |
| Qui lit le compte de chaque lien (`DeclaredOutboundLink`, `LinkCountReader`) | Implémenté et testé |
| Événements comptés par le serveur (`ServerEventCounter`), d'après la déclaration de chaque site | Implémenté et testé |
| Ce que lit un tableau de bord, pour un site ou additionné sur plusieurs (`CounterReader`, `ReaderScope`) | Implémenté et testé |
| Rétention (`CounterRetention`, `UtcMonth`) | Implémenté et testé |
| Vues partagées, rapports et alertes (`SharedView`, `ShareToken`, `ReportPeriod`, `TrafficAlertRule`) | Implémenté et testé |
| `CounterStore`, `CollectionProjection`, `CollectionRateLimiter`, `CountryResolver`, `CrawlerDetector`, `OutboundLinkDirectory`, `CounterRollup` | Contrats, chacun avec un adaptateur par défaut que l'application peut remplacer |
| `SharedViews`, `ReportSubscriptions`, `ReportSender`, `TrafficAlertNotifier` | Contrats, implémentés par l'application |
| Adaptateurs par défaut : `DbalCounterStore`, `DbalCounterRollup`, `CacheSaltStore`, `SymfonyCollectionRateLimiter`, `ConfiguredCollectionProjection`, `UserAgentCrawlerDetector`, `UnknownCountryResolver`, `NoOutboundLinks` | Implémenté et testé |
| Le point de collecte (`Collector`, `CollectController`, route `phil_analytics_collect`) | Implémenté et testé |
| Les tâches quotidiennes de l'horaire `phil_analytics` : rétention, rapports, parcours interrompus avec `phil/broken-flow-alerts`, alertes de trafic (`TrafficAlertRun`) | Implémenté et testé |
| `RobotVerdictCounter`, de `phil/bot-detection-bundle` | Port, pour une application qui tient aussi cet agrégat |
| Le script de collecte, sans témoin ni stockage local : un contrôleur Stimulus (`assets/src/collect_controller.js`), avec son second envoi quand on quitte une page | Implémenté, testé avec Node |
| `PhilAnalyticsBundle`, configuration `phil_analytics` | Implémenté et testé |

## Documentation

- [Installation](doc/fr/installation.md) — prérequis, paquets facultatifs des adaptateurs par défaut, installation
  depuis un dépôt tant que le bundle n'est pas sur Packagist, enregistrement du bundle et import de sa route.
- [Configuration](doc/fr/configuration.md) — l'arbre de `phil_analytics` : les sites, chaque adaptateur par mot-clé ou
  par l'identifiant d'un service de l'application, le point de collecte et les tâches.
- [Compteurs](doc/fr/counters.md) — `SiteId`, `UtcDay`, `CounterKey` : ce qui est stocké, pourquoi le jour est le jour
  UTC, pourquoi les dimensions doivent être des listes fermées, et pourquoi les visites remplacent les visiteurs
  uniques.
- [Routes déclarées](doc/fr/declared-routes.md) — comment un chemin devient un nom de route déclarée ou `(other)`, sans
  la requête ni le fragment, et sans jamais garder les paramètres.
- [Collecte et admission](doc/fr/collection-and-admission.md) — à quel site appartient une requête, l'ordre fixe de la
  collecte, la table d'admission et le point de collecte.
- [Mesures](doc/fr/measures.md) — clics, objectifs et conversion, événements en listes fermées, liens sortants, paires
  de routes, profondeur de lecture, termes de recherche, variantes comme dimension déclarée, et événements comptés par
  le serveur.
- [Rétention](doc/fr/retention.md) — les compteurs quotidiens pendant 13 mois, puis des totaux mensuels.
- [Tableaux de bord, vues partagées, rapports et alertes](doc/fr/sharing-reports-alerts.md) — ce que lit un tableau de
  bord dans les sites que son lecteur peut voir, liens de partage, tableau public, rapports périodiques, et alertes sur
  un pic ou un creux.
- [Tâches planifiées](doc/fr/scheduled-jobs.md) — la rétention, les rapports dus, la vérification des parcours
  interrompus et les alertes de trafic, une fois par jour sur l'horaire `phil_analytics`.
- [Limitation de débit](doc/fr/rate-limiting.md) — l'empreinte d'une adresse IPv4 ou d'un /64 IPv6 sous un sel qui
  s'oublie, et le limiteur fermé en cas d'échec qu'elle alimente.
- [Sources et référents](doc/fr/sources-and-referrers.md) — les six classes de sources, l'ordre du classement, et les
  listes d'hôtes avec l'étiquette de suffixe public `{}`.
- [Appareils et pays](doc/fr/devices-and-countries.md) — trois classes d'appareils d'après l'agent utilisateur, le pays
  cherché en mémoire, et une base périmée qui répond « inconnu ».
- [Signaux de confidentialité](doc/fr/privacy-signals.md) — Do Not Track et Global Privacy Control : qui envoie `1`
  n'est compté nulle part.
- [Masquage des petits nombres](doc/fr/small-number-suppression.md) — regrouper les petits compteurs sous « autre » à
  la lecture, et ne jamais montrer un groupe dont la somme est sous le seuil.
- [Points d'extension](doc/fr/extension-points.md) — chaque contrat, ses invariants, avec un exemple d'adaptateur.
- [Tests](doc/fr/testing.md) — lancer les tests depuis le monodépôt ou avec le bundle installé seul, et tester vos
  adaptateurs.
- [Référence de l'API](doc/fr/reference.md) — chaque type public par couche, avec son rôle et son état.

## Démarrage rapide

```bash
composer require phil/analytics-bundle:^0.1@dev   # from a path or VCS repository until published
```

```yaml
# config/packages/phil_analytics.yaml
phil_analytics:
    sites:
        spring-con:
            origins: ['https://example.com']
            routes: { home: '/', talk: '/talks/{slug}', thanks: '/tickets/thanks' }
            goals: { ticket: { route: thanks } }

# config/routes/phil_analytics.yaml
phil_analytics:
    resource: '@PhilAnalyticsBundle/config/routes.php'
    prefix: /analytics
```

```twig
<body data-controller="phil--analytics-bundle--collect"
      data-phil--analytics-bundle--collect-endpoint-value="{{ url('phil_analytics_collect') }}">
```

Créez les tables des compteurs par une migration, et les pages vues de `https://example.com` sont comptées. Pour les
lire, ne montrez jamais seul un compteur sous le seuil :

```php
use Phil\AnalyticsBundle\Application\Contract\CounterStore;
use Phil\AnalyticsBundle\Domain\Reading\SmallNumberSuppression;
use Phil\AnalyticsBundle\Domain\ValueObject\{SiteId, UtcDay};

$counts = $store->counts(SiteId::fromString('spring-con'), 'pageviews', 'route', UtcDay::fromString('2026-10-01'), UtcDay::fromString('2026-10-31'));
$shown = $suppression->apply($counts);              // SmallNumberSuppression, from the container
```

Le parcours complet de la collecte est dans [doc/fr/collection-and-admission.md](doc/fr/collection-and-admission.md).

## Prérequis

- PHP 8.5 ou plus récent
- `symfony/dependency-injection`, `symfony/config`, `symfony/http-foundation` et `symfony/rate-limiter` 8.2
- Pour les adaptateurs par défaut : Doctrine DBAL et DoctrineBundle ; au besoin Symfony Lock, Scheduler, Messenger,
  Notifier et `phil/broken-flow-alerts`
- `ext-sodium` et `ext-mbstring`
- `phil/ddd-bundle`, `phil/bot-detection-bundle` et `phil/ip-anonymizer` 0.1

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
