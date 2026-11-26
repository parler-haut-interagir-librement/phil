# Changelog

All notable changes to `phil/error-tracking-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-25

### Changed

- The page of a group reads each chain from the top down, the exception thrown first, each previous one marked
  "Caused by" and the root cause last marked "Root cause"; each run of frames under `vendor/` is folded into a
  `<details>` element that tells how many it holds and opens without script.
- The development requirements accept `phil/notifications-bundle` 0.4 and `phil/privacy-bundle` 0.3, the minor versions
  of their main branches and no earlier one, so that the package installs on its own beside those bundles.
- Requires the latest minor of each dependency (`doctrine/dbal` 4.6, `symfony/service-contracts` 3.8), and of each
  development requirement (`doctrine/orm` 3.8, `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31,
  `symfony/ux-twig-component` 3.8, …).
- The development requirements take `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on
  2026-11-24.
- The development requirements take `easycorp/easyadmin-bundle` 5.10.4 or later, the release that fixes
  GHSA-f7rw-7wx5-3c54: a link to a route, like the menu links of this bundle, passed its `routeParams` to the controller
  of that route as arguments.

### Fixed

- The bundle installs and compiles without `phil/monitoring-bundle`: the configuration no longer reads the highest
  threshold of new groups from `ErrorStoreCheck`, whose interface comes from that bundle, but from
  `NewGroupsThreshold::MAX`; the health check is registered only when that bundle is installed and enabled.
- The handler of the queued alerts is tied to the default bus, the one they are dispatched on: an application with a
  query bus beside it no longer finds the handler there, and its container compiles.
- The compiler pass that removes Messenger handlers runs before the one of
  `symfony/messenger`, which Symfony 8.2.0-RC1 registers in its own bundle at the default priority: the container no
  longer fails to build with a handler locator that names a removed service.

### Security

- The throttle of the browser errors counted each client by the HMAC of its address under the secret itself,
  `%kernel.secret%` by default, the same hash other bundles kept for the same address. `BrowserErrorReceiver` derives
  its key by HKDF-SHA256 under `phil_error_tracking.browser_throttle` (`BrowserErrorReceiver::USAGE`).
- Conflicts with `easycorp/easyadmin-bundle` below 5.10.4, the release that fixes GHSA-f7rw-7wx5-3c54: an application
  cannot install this bundle next to a vulnerable EasyAdmin, which stays optional.

## 0.1.0-alpha - 2026-11-20

### Added

- `PhilErrorTrackingBundle` and the `phil_error_tracking` configuration: release, environment, project directory,
  sample rate, bounds, scrubbed fields, token path prefixes filtered in the address, the `Referer` and the `Origin`,
  ignored exceptions, truncated client address, throttle per group, capture
  points, storage, alerts, health check, administration pages off unless enabled, browser endpoint and clock.
- `SafeCapture`: the guard every capture point goes through, which never throws, refuses a capture while another
  runs, logs a failed capture once by its class, and takes a throwable once until the services are reset, the log
  record of its class, file and line included.
- `ExceptionListener` and `RequestSummaries`: the exception of a request captured with its reduced request, before the
  kernel logs it, client errors left out unless asked; the headers that carry the client's raw address through proxies
  never kept.
- `WorkerFailureListener`: a failed message captured once it will not be retried, unwrapped from Messenger's
  wrapper, tagged with its class and transport; the bundle's own alerts never captured.
- `ConsoleErrorListener`: the error of a command, tagged with its name and exit code.
- `ErrorTrackingHandler`: a Monolog handler for the records of a level and above, by their exception or their message;
  an exception a redacting processor such as phil/privacy-bundle's turned into an array keeps its class, its file and
  line, and its group.
- `DbalErrorStore`, `ErrorTables` and `SchemaListener`: the groups and the capped events in `phil_error_group` and
  `phil_error_event`, their instants in `datetime_utc_immutable`; `ErrorGroupReader`, the reads of the pages and the
  purge.
- `PurgeCommand`, `phil:error-tracking:purge`, by retention.
- `StatusCommand`, `phil:error-tracking:status`: the capture points on, whether the store answers and how many groups
  it keeps, and where the alerts go; a store that does not answer fails it, alerts without a destination warn.
- `AdminController` and its templates: the groups by status and page, a group with its latest events and traces, and
  the forms that resolve, ignore or reopen it, for one role, with a CSRF token, and the notice of alerts without a
  destination on both pages; the layout defines the `title` and `main` blocks.
- `ErrorTrackingAdmin` and `@PhilErrorTracking/easyadmin/layout.html.twig`: with EasyAdminBundle registered, the same
  pages grafted on every dashboard through `#[AdminRoute]`, rendered in EasyAdmin's content page, their links and
  redirections on the dashboard's routes, and the link of the dashboard's menu.
- `ChatterAlerter`, `MessengerAlerter`, `AlertErrorGroup` and `AlertErrorGroupHandler`: a chat message for a first
  event or a regression, queued with identifiers only or sent at once; on by default.
- `AlertDestination`, `UndeliveredAlerter` and `AlertsPass`: the degraded state of alerts that are on without a
  destination — no recipient, no audience, no chatter —, which fails nothing and is told by a warning per alert naming
  the group's fingerprint, the administration pages and the status command.
- `NotificationAlerter` and `ErrorGroupSubjects`: the alert as the notification type `phil_error_tracking.group_alert`
  of phil/notifications-bundle, to recipients or an audience, with its translations.
- `ErrorStoreCheck`: the health check of phil/monitoring-bundle, on the store and on a burst of new groups.
- `RateLimiterThrottle`: the throttles of the groups and of the browsers over `symfony/rate-limiter`;
  `LimiterCaptureThrottle`, the browsers counted by the limiter `phil_error_tracking.browser` of phil/rate-limit-bundle;
  the `RateLimitGuard` of phil/rate-limit, required, which refuses a rate limit without its component or, unless
  `single_server` is set, on a pool local to each server in `prod`, each configuration path reported.
- `BrowserErrorParser`, `BrowserError`, `BrowserErrorReceiver`, `Receipt` and `BrowserErrorController`: the endpoint
  of the browsers' errors, checked by media type, size, origin, token and throttle, its parser fuzzed; the page and the
  configured origins read by `phil/url`, the page kept normalised and without its query nor its fragment.
- The Stimulus controller `capture`, which reports the window's uncaught errors and rejections, bounded per page,
  fetched eagerly and mapped for AssetMapper under `@phil/error-tracking-bundle`; its tests under Node's test runner, run
  with the package's suites.
- The documentation in `doc/`, one file per topic, indexed by the README, with a guide from sentry-symfony to the
  capture points.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
- The Flex recipe in `recipe/0.1`: the bundle, `config/packages/phil_error_tracking.yaml`, with the release read from
  `APP_RELEASE` and the administration pages on, and their routes under `/admin/errors`.
