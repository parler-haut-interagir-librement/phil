# Maintenance mode

## Enabling and disabling it

```sh
bin/console phil:monitoring:maintenance enable                  # visitors told to come back in retry_after seconds
bin/console phil:monitoring:maintenance enable --retry-after=900
bin/console phil:monitoring:maintenance status
bin/console phil:monitoring:maintenance disable
```

With the `file` store, the site is in maintenance while the flag file exists: a deployment script can also create it
with `touch var/maintenance.json`. An empty or unreadable file is maintenance since its last change, with the configured
`retry_after`. The file is written atomically, and read afresh on every request, so that every worker of a long-running
server sees it come and go. With the `cache` store, the state is a key of `cache_pool` that never expires.

The services `Maintenance` — `enable()`, `disable()`, `current()` — do the same from the application's code.

## What visitors get

While the site is in maintenance, every main request answers `503 Service Unavailable` with `Retry-After` and
`Cache-Control: no-store`:

- in JSON, `{"status":"maintenance","retry_after":600}`, when the request prefers JSON;
- the template `maintenance.template` otherwise, given the `MaintenanceState` as `maintenance`;
- plain text when the application has no Twig.

The bundle's template stands alone: it extends no layout of the application, which may need what the maintenance took
away. Give your own through `maintenance.template`; keep it free of database calls.

## Who still sees the site

A request is served as usual when one of these allows it, in this order:

1. its route is one of `allowed_routes`, or one of the health endpoints, which are always served;
2. its client address is one of `allowed_ips`, an address or a CIDR network, IPv4 or IPv6;
3. its user is granted one of `allowed_roles` by the authorization checker.

The client address is the one Symfony reads: behind a proxy or a load balancer, declare it in
`framework.trusted_proxies`, or every request comes from the proxy's address. The roles are asked only when the
application has the security component, and only of a request inside a firewall that knows its user.

The listener runs on `kernel.request` after the router and the firewall, so that both the route and the user are known.
A sub-request is never stopped: it belongs to a main request already allowed.
