# phil/feature-flags-bundle

**Turn a feature off without a deploy, run an A/B test, and force a failing third party's circuit open.**

phil/feature-flags-bundle gives a Symfony application two tools. Feature flags withdraw a new feature at once.
A/B tests split contexts between weighted variants. Each circuit breaker of `phil/circuit-breaker-bundle` also gets a
flag that forces it open, so that an operator stops calling a failing third party. Flags are declared in the
configuration or created from admin screens into the database. Each one carries an owner, a kind and an expiry
date, and targets a tenant, a project or a subject, with stable percentage rollouts.

It is for the developers who ship features behind a flag and for the administrators who switch them. Every toggle
and edit is authorized, audited before it is written, and carries a mandatory reason, so the history of a flag
says who changed it and why. The bundle is no authentication layer: the application puts the admin screens behind
its firewall and decides who may change what. It is no client of a hosted flag service, and no analytics tool
either. An A/B exposure is counted by flag and variant, never recorded with its context.

Reach for it to withdraw a new feature. It is no operational kill switch for an established subsystem. That
switch is a short, enumerated list that must keep working when the database or the cache is the problem, so it
is read without this bundle's providers.

A flag is read in a controller attribute, a route condition, Twig or a service. It is evaluated once per request
and context from ordered providers (configuration, then the Doctrine DBAL store), each answer giving its reason. A
profiler panel shows the flags a request evaluated.

## Status

| Area | State |
|---|---|
| Flags, providers, state store, once-per-request evaluation, reasons | Implemented and tested |
| Targeting (membership, stable percentage rollouts) | Implemented and tested |
| A/B experiments and exposure counting | Implemented and tested |
| Metadata and expiry | Implemented and tested |
| Audited toggle and editor, revisions, conflicts | Implemented and tested |
| Database store (Doctrine DBAL, schema listener) | Implemented, tested on SQLite, MySQL 8 and PostgreSQL |
| Admin screens, same-origin guard | Implemented, tested in a kernel |
| Controller attribute, route condition, Twig functions | Implemented, tested in a kernel |
| Listing command with `--fail-on-expired` | Implemented, tested in a kernel |
| A flag per circuit of `phil/circuit-breaker-bundle` that forces it open | Implemented and tested |
| A profiler panel of flags | Implemented, its collector tested |

## Documentation

- [Installation](doc/installation.md) — requirements, Composer, registering the bundle, the database tables and
  their migration, the admin routes and the firewall in front of them.
- [Configuration](doc/configuration.md) — every key of `phil_feature_flags` with its type, default, effect and the
  services it wires, a complete annotated example, and the errors that fail the container build.
- [Flags and evaluation](doc/flags-and-evaluation.md) — definitions, ordered providers and precedence, the state
  store, memoization per request and context, reset in workers, reasons, the context and the catalog.
- [Targeting](doc/targeting.md) — membership and percentage rules, the stable `murmur3a` bucket, and why its
  function is a one-way door.
- [Experiments](doc/experiments.md) — weighted variants, assignment by cumulative weight, reading the variant, and
  exposures counted without context.
- [Metadata and expiry](doc/metadata-and-expiry.md) — description, owner, the four kinds of flag, the expiry date
  and how it fails a build.
- [Toggles and edits](doc/toggles-and-edits.md) — the audited toggle and editor, the order authorize–read–audit–write,
  compare-and-set, first-state inserts, revisions, conflicts and transactions.
- [Database store](doc/database-store.md) — the two tables, the schema listener, per-request reading, conditional
  writes and unreadable rows.
- [Admin](doc/admin.md) — routes, screens, HTTP statuses, the same-origin guard, the actor provider, and overriding
  the layout and templates.
- [Placement](doc/placement.md) — the controller attribute, the route condition, the Twig functions and flags in the
  core.
- [Console](doc/console.md) — `phil:feature-flags:list`, its columns and options, in CI.
- [Circuit breakers](doc/circuit-breakers.md) — the force-open flag of each circuit of `phil/circuit-breaker-bundle`,
  and forcing a circuit open with an audited toggle.
- [Extension points](doc/extension-points.md) — every port with its contract, invariants and an example adapter,
  and every service id, tag, route, Twig function and attribute.
- [Testing](doc/testing.md) — the bundle's suites and databases, mutation testing, and how to test an application
  that uses flags.
- [Reference](doc/reference.md) — the public API by layer, one line per type with its state.

## Requirements

- PHP 8.5 or later, with `ext-mbstring` (and `ext-hash`, always compiled in);
- Symfony 8.2 components (`config`, `console`, `dependency-injection`, `http-foundation`, `http-kernel`, `routing`),
  `twig/twig` 3.21 or later, `psr/clock`, `phil/ddd-bundle`, `phil/circuit-breaker` and
  `phil/circuit-breaker-bundle`;
- Doctrine DBAL 4.5 and ORM 3.7;
- in the application: FrameworkBundle and `symfony/clock`, TwigBundle for the Twig functions and the admin,
  DoctrineBundle for the database store, `symfony/expression-language` for route conditions.

Details in [doc/installation.md](doc/installation.md).

## Quick start

```bash
composer require phil/feature-flags-bundle
```

```php
// config/bundles.php
Phil\FeatureFlagsBundle\PhilFeatureFlagsBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_feature_flags.yaml
phil_feature_flags:
    context_resolver: phil_feature_flags.context_resolver.anonymous   # no targeting yet: an empty context
    flags:
        new-checkout:
            active: true
            owner: checkout-team
            kind: release
            expires_on: '2027-03-31'
```

```php
use Phil\FeatureFlagsBundle\Infrastructure\Http\IsFeatureEnabled;

#[IsFeatureEnabled('new-checkout')]          // 404 while the flag is off
final class CheckoutController
{
    // …
}
```

```twig
{% if feature_enabled('new-checkout') %}<a href="/checkout">Try the new checkout</a>{% endif %}
```

```bash
bin/console phil:feature-flags:list --fail-on-expired
```

Next: a context resolver for targeting, the database store, the audited toggle and the admin — see
[doc/installation.md](doc/installation.md).

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
