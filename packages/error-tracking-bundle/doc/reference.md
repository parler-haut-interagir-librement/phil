# Reference

## Classes

| Class | Role |
|---|---|
| `Phil\ErrorTrackingBundle\PhilErrorTrackingBundle` | The bundle and its configuration, `phil_error_tracking` |
| `Phil\ErrorTrackingBundle\Application\SafeCapture` | The guard of every capture point |
| `Phil\ErrorTrackingBundle\Application\AlertDestination` | Where the alerts go: notification, chat, turned off, or none — the degraded state |
| `Phil\ErrorTrackingBundle\Application\UndeliveredAlerter` | Logs each alert of the degraded state as a warning naming the group's fingerprint |
| `Phil\ErrorTrackingBundle\Application\BrowserErrorParser` | Reads a browser's report |
| `Phil\ErrorTrackingBundle\Application\BrowserError` | A browser's report, read and bounded |
| `Phil\ErrorTrackingBundle\Application\BrowserErrorReceiver` | The checks of the endpoint, then the capture |
| `Phil\ErrorTrackingBundle\Application\Receipt` | The outcome of a report, with its HTTP status |
| `Phil\ErrorTrackingBundle\Application\Contract\ErrorGroupReader` | The reads of the pages and the purge |
| `Phil\ErrorTrackingBundle\Application\Message\AlertErrorGroup` | A queued alert: a fingerprint and an occurrence |
| `Phil\ErrorTrackingBundle\Application\Exception\InvalidBrowserError` | A report the parser cannot read |
| `Phil\ErrorTrackingBundle\Infrastructure\Http\ExceptionListener` | The exception of a request |
| `Phil\ErrorTrackingBundle\Infrastructure\Http\RequestSummaries` | The reduced request |
| `Phil\ErrorTrackingBundle\Infrastructure\Http\BrowserErrorController` | The endpoint of the browsers |
| `Phil\ErrorTrackingBundle\Infrastructure\Http\AdminController` | The administration pages |
| `Phil\ErrorTrackingBundle\Infrastructure\Messenger\WorkerFailureListener` | A failed message |
| `Phil\ErrorTrackingBundle\Infrastructure\Messenger\MessengerAlerter` | Queues an alert |
| `Phil\ErrorTrackingBundle\Infrastructure\Messenger\AlertErrorGroupHandler` | Sends a queued alert |
| `Phil\ErrorTrackingBundle\Infrastructure\Notifier\ChatterAlerter` | Sends an alert through the chatter |
| `Phil\ErrorTrackingBundle\Infrastructure\Notifications\NotificationAlerter` | Sends an alert through phil/notifications-bundle |
| `Phil\ErrorTrackingBundle\Infrastructure\Notifications\ErrorGroupSubjects` | The label and address of an alert's group, when it is shown |
| `Phil\ErrorTrackingBundle\Infrastructure\Monitoring\ErrorStoreCheck` | The health check of phil/monitoring-bundle |
| `Phil\ErrorTrackingBundle\Application\NewGroupsThreshold` | The highest threshold of new groups of the health check |
| `Phil\ErrorTrackingBundle\Infrastructure\DependencyInjection\AlertsPass` | Without a chatter, puts the alerts in the degraded state; without a bus, drops their queue; else ties their handler to the default bus |
| `Phil\ErrorTrackingBundle\Infrastructure\Console\ConsoleErrorListener` | The error of a command |
| `Phil\ErrorTrackingBundle\Infrastructure\Console\PurgeCommand` | `phil:error-tracking:purge` |
| `Phil\ErrorTrackingBundle\Infrastructure\Console\StatusCommand` | `phil:error-tracking:status` |
| `Phil\ErrorTrackingBundle\Infrastructure\Monolog\ErrorTrackingHandler` | The Monolog handler |
| `Phil\ErrorTrackingBundle\Infrastructure\Doctrine\DbalErrorStore` | The groups and events through DBAL |
| `Phil\ErrorTrackingBundle\Infrastructure\Doctrine\ErrorTables` | The two tables |
| `Phil\ErrorTrackingBundle\Infrastructure\Doctrine\SchemaListener` | The tables in the ORM's schema |
| `Phil\ErrorTrackingBundle\Infrastructure\RateLimiter\RateLimiterThrottle` | A throttle over `symfony/rate-limiter` |
| `Phil\ErrorTrackingBundle\Infrastructure\RateLimit\LimiterCaptureThrottle` | A throttle over a limiter of phil/rate-limit-bundle |

## Services

| Id | Public | What |
|---|---|---|
| `Phil\ErrorTracking\ErrorTracker` | yes | The tracker |
| `Phil\ErrorTracking\EventFactory` | yes | The factory, with the release, environment and project directory |
| `Phil\ErrorTrackingBundle\Application\SafeCapture` | yes | The guard, reset with `kernel.reset` |
| `Phil\ErrorTracking\Contract\ErrorStore`, `…\ErrorGroupReader` | yes | Aliases of `DbalErrorStore` |
| `phil_error_tracking.monolog_handler` | yes | The Monolog handler |
| `Phil\ErrorTrackingBundle\Application\AlertDestination` | yes | The destination of the alerts, as the container was built |
| `phil_error_tracking.ip_anonymizer` | no | The truncation of the client's address |
| `phil_error_tracking.group_throttle`, `phil_error_tracking.browser_throttle` | no | The throttles |
| `phil_error_tracking.browser_throttle.limit` | no | The limiter `phil_error_tracking.browser` of phil/rate-limit-bundle, with that bundle |

## Commands

| Name | What |
|---|---|
| `phil:error-tracking:purge` | Removes the events older than the retention, and the groups not seen since |
| `phil:error-tracking:status` | Tells the capture points, whether the store answers, and where the alerts go |

## Routes

| Name | Method and path | File |
|---|---|---|
| `phil_error_tracking_groups` | `GET /` | `config/routes.php` |
| `phil_error_tracking_group` | `GET /{fingerprint}` | `config/routes.php` |
| `phil_error_tracking_group_status` | `POST /{fingerprint}/{resolve,ignore,reopen}` | `config/routes.php` |
| `phil_error_tracking_browser` | `POST /_errors/browser` | `config/routes_browser.php` |
| `{dashboard}_errors_groups`, `{dashboard}_errors_group`, `{dashboard}_errors_group_status` | the same three pages under `{dashboard path}/errors` | `#[AdminRoute]` on `AdminController`, with EasyAdminBundle registered |

## Templates and assets

| Template | What |
|---|---|
| `@PhilErrorTracking/layout.html.twig` | The default layout |
| `@PhilErrorTracking/groups.html.twig` | The list of the groups |
| `@PhilErrorTracking/group.html.twig` | A group and its events |
| `@PhilErrorTracking/_alerts.html.twig` | The notice of the degraded state, included by both pages |
| `@PhilErrorTracking/easyadmin/layout.html.twig` | The layout of the pages in an EasyAdmin back office |
| `assets/src/capture_controller.js` | The Stimulus controller `capture` |
| `assets/src/capture.js` | The report and its sending |
