# Extension points

## The store

`ErrorStore` (of `phil/error-tracking`) and `ErrorGroupReader` (of this bundle, in
`Phil\ErrorTrackingBundle\Application\Contract`) are aliases of `DbalErrorStore`. An application that keeps its
errors elsewhere replaces both aliases with a service implementing the two interfaces:

```php
interface ErrorGroupReader
{
    /** @return list<ErrorGroup> */
    public function groups(?GroupStatus $status, int $limit, int $offset = 0): array;

    public function countGroups(?GroupStatus $status): int;

    /** @return list<ErrorEvent> */
    public function events(string $fingerprint, int $limit): array;

    public function purge(DateTimeImmutable $before): int;
}
```

`groups()` gives the groups seen last first; `events()` the latest first; `purge()` removes the events older than the
instant and the groups not seen since, and returns how many rows went.

## The alerter

The tracker's alerter is a `GroupAlerter` of `phil/error-tracking`: `NotificationAlerter`, `MessengerAlerter` or
`ChatterAlerter`, by the configuration, or `UndeliveredAlerter` in the degraded state, when none of them has a
destination. An application that alerts otherwise decorates
`Phil\ErrorTracking\ErrorTracker` with its own arguments, or implements `GroupAlerter` and passes it as the seventh
argument of the tracker's definition. A queued alert reaches `AlertErrorGroupHandler`, whose second argument is the
alerter that sends.

## The health check

With phil/monitoring-bundle, `ErrorStoreCheck` is a `HealthCheck` named `error_tracking`, autoconfigured. It reads
`ErrorGroupReader::groups()`, 200 groups at most, and is degraded when the store does not answer or when
`health_check.new_groups` groups appeared within `health_check.window` seconds; only counts reach its summary.

## Ignored exceptions

`ignored_exceptions` names the classes never captured, with their subclasses: an exception the application throws on
purpose and handles elsewhere, an expected time-out.

## The scrubber

`scrub_fields` adds parts of a name to the sensitive ones; see the scrubbing document of `phil/error-tracking`. A
scrubber of the application's replaces the `Phil\ErrorTracking\Scrubber` service.

`token_path_prefixes` declares the routes that carry a token in their path, by the prefix the token follows: with
`/reset-password/reset/` declared, `/reset-password/reset/a1b2c3` is kept as `/reset-password/reset/[Filtered]`, in
the request's address, in `Referer` and in `Origin`. A prefix matches where the path starts only. A token in the path
survives the filtering of the query, so every route of the application that carries one — a password reset, an
invitation, a link shared by its token — belongs in the list:

```yaml
phil_error_tracking:
    token_path_prefixes: ['/reset-password/reset/', '/invitation/']
```

The headers through which proxies pass on the client's raw address — `X-Forwarded-For`, `Forwarded`, `X-Real-IP`,
`CF-Connecting-IP`, `True-Client-IP`, `X-Client-IP` and the others — are never kept; the client's address is kept as
`client_address` truncates it.

## The layout and templates

`admin.layout` gives the pages the application's layout, which defines the `title` and `main` blocks; the templates
can be overridden under `templates/bundles/PhilErrorTrackingBundle/`. With EasyAdminBundle registered, the same pages
are grafted on every dashboard, and `ErrorTrackingAdmin::menuItem()` gives the dashboard's menu its link. See
[Administration](administration.md).
