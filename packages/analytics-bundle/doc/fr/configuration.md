<!-- phil-dev source-sha256=5aa72600f8eae73b9abdea0603707811948743b45ce122edbaae8509a46742ac -->
# Configuration

Le bundle configure ses services du domaine à partir de `phil_analytics`, et un adaptateur par contrat. Chaque
adaptateur est nommé par un mot-clé pour celui du bundle, ou par l'identifiant d'un service de l'application qui
implémente le même contrat : le bundle fait alors du contrat un alias de ce service et n'enregistre rien à sa place.

## L'arbre complet, avec ses valeurs par défaut

```yaml
# config/packages/phil_analytics.yaml
phil_analytics:
    small_number_threshold: 5          # at least 2
    single_server: false
    hosts:
        search: ['google.{}', 'bing.com', 'duckduckgo.com', ...]   # see below
        social: ['facebook.com', 'mastodon.social', ...]
        spam: []
    sites: {}                          # the sites of the "config" projection
    salt:
        store: cache                   # or the id of an ExpiringSaltStore
        cache_pool: cache.app
        lock_factory: lock.factory     # null for none
    counters:
        store: dbal                    # or the id of a CounterStore
        rollup: ~                      # the id of a CounterRollup, when the store is not "dbal"
        connection: default
    rate_limiter:
        limiter: symfony               # or the id of a CollectionRateLimiter
        limit: 600
        interval: '10 minutes'
        cache_pool: cache.app
        lock_factory: ~
    collection:
        enabled: true
        projection: config             # or the id of a CollectionProjection
        crawler_detector: user_agent   # or the id of a CrawlerDetector
        country_resolver: unknown      # or the id of a CountryResolver
        outbound_links: none           # or the id of an OutboundLinkDirectory
    retention:
        enabled: true
        months: 13
    reports:
        enabled: false
        subscriptions: ~               # the id of a ReportSubscriptions
        sender: ~                      # the id of a ReportSender
    broken_flows:
        enabled: false
        flows: {}
        baseline_days: 28
        window_days: 7
        significance: 0.001
        weekly: true
        sender: ~                      # the id of an AlertSender; null, through the notifier
    traffic_alerts:
        enabled: false
        sites: []                      # the references of the watched sites
        notifier: ~                    # the id of a TrafficAlertNotifier
        spike_percent: 300
        dip_percent: 30
        minimum_baseline: 20
        baseline_days: 28              # 7 to 366
    schedule:
        enabled: true
        time: '05:00'                  # UTC
```

`bin/console config:dump-reference phil_analytics` affiche le même arbre avec l'aide de chaque clé.

## Réglages du domaine

| Clé | Par défaut | Devient |
|---|---|---|
| `small_number_threshold` | `5` | `SmallNumberThreshold`, lu par `SmallNumberSuppression` et par le cumul des dimensions ouvertes. Sous 2, la construction échoue. |
| `single_server` | `false` | Dans l'environnement `prod`, la construction refuse un `salt.cache_pool` ou un `rate_limiter.cache_pool` propre à chaque serveur — le `cache.app` sur disque parmi eux —, où chaque serveur tirerait son propre sel et où un client dépenserait son budget une fois par serveur ; `true` l'accepte, pour une application sur un seul serveur. |
| `hosts.search` | Dix moteurs de recherche : `google.{}`, `bing.com`, `duckduckgo.com`, `search.yahoo.com`, `yandex.{}`, `baidu.com`, `ecosia.org`, `qwant.com`, `search.brave.com`, `startpage.com` | La `HostList` des moteurs de recherche de `ReferrerClassifier` |
| `hosts.social` | Douze réseaux : `facebook.com`, `instagram.com`, `linkedin.com`, `reddit.com`, `x.com`, `twitter.com`, `t.co`, `youtube.com`, `pinterest.com`, `threads.net`, `bsky.app`, `mastodon.social` | La `HostList` des réseaux sociaux |
| `hosts.spam` | aucun | La `HostList` de pourriel, par exemple chargée d'une liste CC0 de pourriel de référents |

Donner une liste remplace sa valeur par défaut au lieu de s'y ajouter. Un hôte que refuse l'objet-valeur se manifeste
par une `InvalidValue` à la première construction de `ReferrerClassifier`, pas à la compilation du conteneur.

## Les sites : `sites`

La projection `config` déclare les sites connus au déploiement de l'application :

```yaml
phil_analytics:
    sites:
        spring-con:
            origins: ['https://example.com', 'https://www.example.com']
            routes:
                home: '/'
                talk: '/talks/{slug}'
                thanks: '/tickets/thanks'
            status: active                 # active, suspended or retired
            goals:
                ticket: { route: thanks }
                contact: { event: form_submit, value: contact }
            forms: [contact]
            click_names: [cta-buy]
            clicks: aggregate              # or off
            downloads: ~                   # null for the default extensions, or a list such as [pdf, zip]
            dimensions:
                checkout_button: [a, b]    # the variant of an experiment, a plan, a language
            server_events: [signup, ticket_sold]
```

La clé de chaque site est son `SiteId`, gardé tel qu'écrit (tirets compris). Chaque site demande au moins une origine,
et une origine n'appartient qu'à un site : deux sites qui la déclarent sont refusés à la première construction de la
projection.
Un objectif nomme une route déclarée, ou un événement mesuré et sa valeur, jamais les deux. Une application dont les
sites changent pendant l'exécution nomme plutôt sa propre `CollectionProjection` sous `collection.projection`.

| Clé | Par défaut | Devient |
|---|---|---|
| `clicks` | `aggregate` | `ClickTracking` : un compteur par nom de clic déclaré, lié à personne ; `off` ne mesure aucun clic, téléchargement ni lien sortant |
| `downloads` | `null`, les extensions par défaut de `ClosedList::downloads()` | Les extensions comptées comme téléchargements |
| `dimensions` | aucune | Une `DeclaredDimension` par nom, chacune avec sa liste fermée de valeurs |
| `server_events` | aucun | Les événements que `ServerEventCounter` compte pour le site ([measures.md](measures.md#événements-comptés-par-le-serveur)) |

## Les adaptateurs

| Clé | Adaptateur du bundle | Contrat |
|---|---|---|
| `salt.store: cache` | `CacheSaltStore`, sous `ForgottenSalt` (un nouveau sel aléatoire à chaque jour UTC) | `Phil\IpAnonymizer\Contract\ExpiringSaltStore` |
| `counters.store: dbal` | `DbalCounterStore` et `DbalCounterRollup` | `CounterStore`, `CounterRollup` |
| `rate_limiter.limiter: symfony` | `SymfonyCollectionRateLimiter`, une fenêtre glissante de Symfony Rate Limiter | `CollectionRateLimiter` |
| `collection.projection: config` | `ConfiguredCollectionProjection`, les sites ci-dessus | `CollectionProjection` |
| `collection.crawler_detector: user_agent` | `UserAgentCrawlerDetector` | `CrawlerDetector` |
| `collection.country_resolver: unknown` | `UnknownCountryResolver` : tous les pays inconnus | `CountryResolver` |
| `collection.outbound_links: none` | `NoOutboundLinks` : les clics sortants comptés par hôte seulement | `OutboundLinkDirectory` |

Chaque contrat est aussi un alias public du conteneur : l'application peut le récupérer, ou le redéfinir dans son propre
`services.yaml`, lu après la configuration du bundle.

### Le sel

`CacheSaltStore` garde le sel de chaque jour dans `salt.cache_pool` jusqu'à la fin du jour, puis la réserve l'oublie. La
réserve doit être **partagée par tous les serveurs** de collecte, sinon chacun tire son propre sel et le budget du
limiteur est multiplié par le nombre de serveurs ; et elle ne doit **pas être persistée**, sinon les sels qu'elle devait
oublier relient de nouveau deux jours d'empreintes. Une réserve Redis ou Memcached sans persistance convient aux deux ;
la valeur par défaut `cache.app` convient à un seul serveur dont la cache sur disque n'est pas sauvegardée. Avec une
fabrique de verrous partagée par les serveurs, la première réclamation d'un jour est atomique entre eux. Le service
`phil_analytics.salt_provider` est le `SaltProvider` de `ClientDigester`.

### Les compteurs

`DbalCounterStore` et `DbalCounterRollup` utilisent la connexion Doctrine `counters.connection`, dans deux tables,
`phil_analytics_counter` et `phil_analytics_monthly_counter`. Avec DoctrineBundle et l'ORM, les tables rejoignent le
schéma que génère l'ORM, pour que `doctrine:migrations:diff` écrive la migration qui les crée.

**Sans DoctrineBundle**, le stockage `dbal` n'a pas de connexion : le bundle démarre, et le point de collecte et les
tâches ne sont pas configurés tant que l'application ne nomme pas un `CounterStore` sous `counters.store` (et un
`CounterRollup` sous `counters.rollup`, pour la rétention).

### Le limiteur de débit

Une fenêtre glissante de `limit` envois par `interval`, par site et par empreinte de client à clé, dont l'état vit dans
`rate_limiter.cache_pool`. Calibrez la limite sur un groupe derrière une seule adresse (le Wi-Fi d'un congrès, une
traduction d'adresses d'opérateur), en comptant plusieurs envois par page : la page vue, le second envoi quand on la
quitte, un par événement. Le limiteur est fermé en cas d'échec : une erreur de la réserve refuse la page vue
([rate-limiting.md](rate-limiting.md)).

## Le point de collecte

Avec `collection.enabled` et un stockage des compteurs, le bundle enregistre `Collector` et `CollectController`.
Importez la route :

```yaml
# config/routes/phil_analytics.yaml
phil_analytics:
    resource: '@PhilAnalyticsBundle/config/routes.php'
    prefix: /analytics
```

La route `phil_analytics_collect` répond à `POST /analytics/collect` sans corps
([collection-and-admission.md](collection-and-admission.md#le-point-de-collecte)).

## Les tâches quotidiennes

`retention`, `reports`, `broken_flows` et `traffic_alerts` sont des tâches lancées une fois par jour sur l'horaire `phil_analytics`, à
`schedule.time` en UTC ([scheduled-jobs.md](scheduled-jobs.md)) :

| Clé | Configurée quand | Demande |
|---|---|---|
| `retention` | activée (par défaut) et le stockage a un cumul | — |
| `reports` | activée | `reports.subscriptions` et `reports.sender` ; l'activer sans les deux fait échouer la construction |
| `broken_flows` | activée | `phil/broken-flow-alerts` ; le service `notifier`, à moins que `broken_flows.sender` nomme un `AlertSender` |
| `traffic_alerts` | activée | `traffic_alerts.notifier` et au moins un site sous `traffic_alerts.sites` ; l'activer sans les deux fait échouer la construction |
| `schedule` | activée (par défaut) et `symfony/scheduler` est installé | `symfony/messenger`, pour consommer `scheduler_phil_analytics` |

## Erreurs à la construction

| Configuration | Erreur |
|---|---|
| Une clé que l'arbre ne déclare pas | `Unrecognized option "foo" under "phil_analytics".` |
| `small_number_threshold: 1` | `The value 1 is too small for path "phil_analytics.small_number_threshold".` |
| Un site sans origine | `The path "phil_analytics.sites.<site>.origins" should have at least 1 element(s) defined.` |
| Un objectif avec à la fois une route et un événement, ou un événement sans valeur | `A goal names a declared route, or a measured event and its value` |
| `reports.enabled` sans `subscriptions` ou `sender` | `Reports need the ids of the ReportSubscriptions and ReportSender services` |
| `schedule.time` qui n'est pas `HH:MM` | `The time of the jobs is HH:MM, in UTC` |
| En `prod`, un pool du sel ou du limiteur propre à chaque serveur, sans `single_server` | `phil_analytics.rate_limiter.cache_pool: the store "cache.app" is local to each server, so a client could spend the limit once per server. …` |
| `broken_flows.significance` à 0 ou moins, ou à 0.5 ou plus | `The significance of a broken flow is above 0 and under 0.5` |
| `broken_flows.enabled` sans `phil/broken-flow-alerts` | `phil_analytics.broken_flows needs phil/broken-flow-alerts` |
| `traffic_alerts.enabled` sans `notifier` ou sans `sites` | `Traffic alerts need the id of a TrafficAlertNotifier service and at least one watched site` |
| Un événement du serveur qui ne commence pas par une lettre, ou de plus de 50 caractères | `"<event>" cannot be a server event`, à la première construction de la projection |
