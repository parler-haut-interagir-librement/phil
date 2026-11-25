<!-- phil-dev source-sha256=d2b1794ac649857a52b9b8376c189d59c84d09e8adcb09a82989d7ad7f873b3d -->
# Référence

## Classes

| Classe | Rôle |
|---|---|
| `Phil\ErrorTrackingBundle\PhilErrorTrackingBundle` | Le bundle et sa configuration, `phil_error_tracking` |
| `Phil\ErrorTrackingBundle\Application\SafeCapture` | La garde de chaque point de capture |
| `Phil\ErrorTrackingBundle\Application\AlertDestination` | Où vont les alertes : notification, clavardage, désactivées ou nulle part — l'état dégradé |
| `Phil\ErrorTrackingBundle\Application\UndeliveredAlerter` | Journalise chaque alerte de l'état dégradé en avertissement nommant l'empreinte du groupe |
| `Phil\ErrorTrackingBundle\Application\BrowserErrorParser` | Lit le rapport d'un navigateur |
| `Phil\ErrorTrackingBundle\Application\BrowserError` | Le rapport d'un navigateur, lu et borné |
| `Phil\ErrorTrackingBundle\Application\BrowserErrorReceiver` | Les vérifications du point de terminaison, puis la capture |
| `Phil\ErrorTrackingBundle\Application\Receipt` | L'issue d'un rapport, avec son code HTTP |
| `Phil\ErrorTrackingBundle\Application\Contract\ErrorGroupReader` | Les lectures des pages et la purge |
| `Phil\ErrorTrackingBundle\Application\Message\AlertErrorGroup` | Une alerte en file d'attente : une empreinte et une occurrence |
| `Phil\ErrorTrackingBundle\Application\Exception\InvalidBrowserError` | Un rapport que l'analyseur ne peut pas lire |
| `Phil\ErrorTrackingBundle\Infrastructure\Http\ExceptionListener` | L'exception d'une requête |
| `Phil\ErrorTrackingBundle\Infrastructure\Http\RequestSummaries` | La requête réduite |
| `Phil\ErrorTrackingBundle\Infrastructure\Http\BrowserErrorController` | Le point de terminaison des navigateurs |
| `Phil\ErrorTrackingBundle\Infrastructure\Http\AdminController` | Les pages d'administration |
| `Phil\ErrorTrackingBundle\Infrastructure\Messenger\WorkerFailureListener` | Un message en échec |
| `Phil\ErrorTrackingBundle\Infrastructure\Messenger\MessengerAlerter` | Met une alerte en file d'attente |
| `Phil\ErrorTrackingBundle\Infrastructure\Messenger\AlertErrorGroupHandler` | Envoie une alerte en file d'attente |
| `Phil\ErrorTrackingBundle\Infrastructure\Notifier\ChatterAlerter` | Envoie une alerte par le *chatter* |
| `Phil\ErrorTrackingBundle\Infrastructure\Notifications\NotificationAlerter` | Envoie une alerte par phil/notifications-bundle |
| `Phil\ErrorTrackingBundle\Infrastructure\Notifications\ErrorGroupSubjects` | Le libellé et l'adresse du groupe d'une alerte, quand elle est affichée |
| `Phil\ErrorTrackingBundle\Infrastructure\Monitoring\ErrorStoreCheck` | La vérification de santé de phil/monitoring-bundle |
| `Phil\ErrorTrackingBundle\Application\NewGroupsThreshold` | Le seuil le plus haut de nouveaux groupes de la vérification de santé |
| `Phil\ErrorTrackingBundle\Infrastructure\DependencyInjection\AlertsPass` | Sans *chatter*, met les alertes dans l'état dégradé ; sans bus, retire leur file d'attente ; sinon, lie leur gestionnaire au bus par défaut |
| `Phil\ErrorTrackingBundle\Infrastructure\Console\ConsoleErrorListener` | L'erreur d'une commande |
| `Phil\ErrorTrackingBundle\Infrastructure\Console\PurgeCommand` | `phil:error-tracking:purge` |
| `Phil\ErrorTrackingBundle\Infrastructure\Console\StatusCommand` | `phil:error-tracking:status` |
| `Phil\ErrorTrackingBundle\Infrastructure\Monolog\ErrorTrackingHandler` | Le gestionnaire Monolog |
| `Phil\ErrorTrackingBundle\Infrastructure\Doctrine\DbalErrorStore` | Les groupes et les événements par DBAL |
| `Phil\ErrorTrackingBundle\Infrastructure\Doctrine\ErrorTables` | Les deux tables |
| `Phil\ErrorTrackingBundle\Infrastructure\Doctrine\SchemaListener` | Les tables dans le schéma de l'ORM |
| `Phil\ErrorTrackingBundle\Infrastructure\RateLimiter\RateLimiterThrottle` | Un limiteur sur `symfony/rate-limiter` |
| `Phil\ErrorTrackingBundle\Infrastructure\RateLimit\LimiterCaptureThrottle` | Un limiteur sur un limiteur de phil/rate-limit-bundle |

## Services

| Identifiant | Public | Quoi |
|---|---|---|
| `Phil\ErrorTracking\ErrorTracker` | oui | Le traqueur |
| `Phil\ErrorTracking\EventFactory` | oui | La fabrique, avec la version, l'environnement et le répertoire du projet |
| `Phil\ErrorTrackingBundle\Application\SafeCapture` | oui | La garde, réinitialisée par `kernel.reset` |
| `Phil\ErrorTracking\Contract\ErrorStore`, `…\ErrorGroupReader` | oui | Alias de `DbalErrorStore` |
| `phil_error_tracking.monolog_handler` | oui | Le gestionnaire Monolog |
| `Phil\ErrorTrackingBundle\Application\AlertDestination` | oui | La destination des alertes, telle que le conteneur a été construit |
| `phil_error_tracking.ip_anonymizer` | non | La troncature de l'adresse du client |
| `phil_error_tracking.group_throttle`, `phil_error_tracking.browser_throttle` | non | Les limiteurs |
| `phil_error_tracking.browser_throttle.limit` | non | Le limiteur `phil_error_tracking.browser` de phil/rate-limit-bundle, avec ce bundle |

## Commandes

| Nom | Quoi |
|---|---|
| `phil:error-tracking:purge` | Retire les événements plus anciens que la durée de conservation, et les groupes qui n'ont pas été vus depuis |
| `phil:error-tracking:status` | Dit les points de capture, si le magasin répond, et où vont les alertes |

## Routes

| Nom | Méthode et chemin | Fichier |
|---|---|---|
| `phil_error_tracking_groups` | `GET /` | `config/routes.php` |
| `phil_error_tracking_group` | `GET /{fingerprint}` | `config/routes.php` |
| `phil_error_tracking_group_status` | `POST /{fingerprint}/{resolve,ignore,reopen}` | `config/routes.php` |
| `phil_error_tracking_browser` | `POST /_errors/browser` | `config/routes_browser.php` |
| `{dashboard}_errors_groups`, `{dashboard}_errors_group`, `{dashboard}_errors_group_status` | les trois mêmes pages sous `{chemin du tableau de bord}/errors` | `#[AdminRoute]` sur `AdminController`, avec EasyAdminBundle enregistré |

## Gabarits et ressources

| Gabarit | Quoi |
|---|---|
| `@PhilErrorTracking/layout.html.twig` | La mise en page par défaut |
| `@PhilErrorTracking/groups.html.twig` | La liste des groupes |
| `@PhilErrorTracking/group.html.twig` | Un groupe et ses événements |
| `@PhilErrorTracking/_alerts.html.twig` | L'avis de l'état dégradé, inclus par les deux pages |
| `@PhilErrorTracking/easyadmin/layout.html.twig` | La mise en page des pages dans une interface d'administration EasyAdmin |
| `assets/src/capture_controller.js` | Le contrôleur Stimulus `capture` |
| `assets/src/capture.js` | Le rapport et son envoi |
