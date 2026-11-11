# Configuration reference

Everything under the `phil_feature_flags` key, as `PhilFeatureFlagsBundle::configure()` declares it, and what
each option wires. Run `bin/console config:dump-reference phil_feature_flags` for the tree as Symfony prints it.

## Annotated example

```yaml
# config/packages/phil_feature_flags.yaml
phil_feature_flags:
    # Service id of the resolver of the evaluation context of a request.
    # Default: the service id `Phil\FeatureFlagsBundle\Application\Flag\EvaluationContextResolver`.
    # `phil_feature_flags.context_resolver.anonymous` gives every request an empty context.
    context_resolver: App\Flags\RequestContextResolver

    # The database store. Off by default; `doctrine: true` or any map turns it on.
    doctrine:
        connection: default            # DBAL connection name; default "default"

    # Provider service ids, in order of precedence. Default: the configuration provider, then the
    # database store when `doctrine` is enabled.
    providers:
        - phil_feature_flags.provider.configuration
        - phil_feature_flags.provider.doctrine

    # Service id of the store of active states. Default: the database store when enabled, otherwise none.
    state_store: phil_feature_flags.state_store.doctrine

    # Service id of the store the admin creates and edits flags in. Default: the database store when enabled.
    definition_store: phil_feature_flags.provider.doctrine

    # Service id of the recorder of A/B exposures. Default: none.
    exposure_recorder: App\Flags\ExposureCounter

    # The audited toggle and edit. Off by default; any map turns it on.
    toggle:
        authorizer: App\Flags\PermissionAuthorizer   # default: the ToggleAuthorizer interface id
        auditor: App\Flags\AuditTrailAuditor         # default: the ToggleAuditor interface id

    # The admin screens. Off by default; any map turns them on. They need `toggle`.
    admin:
        actor_provider: App\Flags\OperatorProvider   # default: the ActorProvider interface id
        layout: 'admin/layout.html.twig'             # default: '@PhilFeatureFlags/admin/layout.html.twig'

    # Service id of the PSR-20 clock. Default: "clock".
    clock: clock

    # The flags of the configuration provider, by name.
    flags:
        new-checkout:
            active: true
            rules:
                - { attribute: tenant, members: [tenant-a, tenant-b] }
                - { attribute: tenant, percentage: 20 }
            description: The one-page checkout.
            owner: checkout-team
            kind: release
            expires_on: '2027-03-31'
        checkout-layout:
            active: true
            experiment:
                attribute: tenant
                variants: { one-column: 50, two-columns: 50 }
            kind: experiment
        search.v2: false               # short for { active: false }
        maintenance-banner: true       # short for { active: true }: on for everyone
```

## Keys

### `context_resolver`

| | |
|---|---|
| Type | string (service id), cannot be empty |
| Default | `Phil\FeatureFlagsBundle\Application\Flag\EvaluationContextResolver` |
| Effect | Second argument of `FlagEvaluator`. Called on every evaluation, so it stays cheap. |

The default is the interface name, so the application either registers a service under that id (an autowiring
alias does) or names its own. `phil_feature_flags.context_resolver.anonymous` (`AnonymousContextResolver`) is
always registered: every context is empty, so a flag with rules or an experiment is off for everyone and only
flags without rules are on. See [flags-and-evaluation.md](flags-and-evaluation.md).

### `doctrine`

| Key | Type | Default | Effect |
|---|---|---|---|
| `doctrine` | boolean or map (`canBeEnabled`) | disabled | `true`, `~` or a non-empty map enables it; `false` disables it. |
| `doctrine.enabled` | boolean | `false` | Set by the forms above. |
| `doctrine.connection` | string, cannot be empty | `default` | The DBAL connection; the bundle references the service `doctrine.dbal.<connection>_connection`. |

When enabled, the bundle registers:

- `phil_feature_flags.provider.doctrine` — `DoctrineFlagDefinitions`, tagged `kernel.reset`: a provider and the
  writable definition store;
- `phil_feature_flags.state_store.doctrine` — `DoctrineFlagStore`, tagged `kernel.reset`: the writable store of
  active states;
- `FlagSchemaListener`, tagged `doctrine.event_listener` on `postGenerateSchema` for that connection.

It also changes the defaults of `providers`, `state_store` and `definition_store`. See
[database-store.md](database-store.md).

### `providers`

| | |
|---|---|
| Type | list of strings (service ids), each non-empty |
| Default | `[phil_feature_flags.provider.configuration]`, plus `phil_feature_flags.provider.doctrine` when `doctrine` is enabled |
| Effect | First argument of `FlagEvaluator` and of `FlagCatalog`, in this order. |

The first provider that knows a flag gives its whole definition; an empty list means the default. The catalog
keys the providers by their service id, which is what the listing and the admin show as **Source**. Each service
implements `FlagProvider`; implement `EnumerableFlagProvider` to appear in the listing. Naming
`phil_feature_flags.provider.doctrine` without enabling `doctrine` fails the container compilation with a missing
service. Leave out `phil_feature_flags.provider.configuration` and the `flags` of the configuration are never
read (the provider is still registered).

### `state_store`

| | |
|---|---|
| Type | string (service id) or `null` |
| Default | `null`, or `phil_feature_flags.state_store.doctrine` when `doctrine` is enabled |
| Effect | Third argument of `FlagEvaluator` and second of `FlagCatalog`; the store `FlagToggler` and `FlagEditor` write to. |

Without `toggle`, any `FlagStateSource` will do. With `toggle`, it must be a `WritableFlagStore`.

### `definition_store`

| | |
|---|---|
| Type | string (service id) or `null` |
| Default | `null`, or `phil_feature_flags.provider.doctrine` when `doctrine` is enabled |
| Effect | The `WritableFlagDefinitions` the editor writes to. With `toggle` enabled, it registers `FlagEditor`; the admin's create and edit screens exist only then. |

The admin shows the edit link only for flags whose catalog source is this exact service id, so the definition
store should also be one of the `providers`.

### `exposure_recorder`

| | |
|---|---|
| Type | string (service id) or `null` |
| Default | `null` |
| Effect | Fourth argument of `FlagEvaluator`: an `ExposureRecorder` told the flag and the variant once per flag and context per request. |

See [experiments.md](experiments.md).

### `toggle`

| Key | Type | Default | Effect |
|---|---|---|---|
| `toggle` | boolean or map (`canBeEnabled`) | disabled | Enables `FlagToggler` (and `FlagEditor` when there is a definition store). |
| `toggle.authorizer` | string (service id), non-empty | `Phil\FeatureFlagsBundle\Application\Toggle\ToggleAuthorizer` | Authorizes every toggle, creation and edit. |
| `toggle.auditor` | string (service id), non-empty | `Phil\FeatureFlagsBundle\Application\Toggle\ToggleAuditor` | Writes the audit line of every change. |

Registers `FlagToggler` (authorizer, state store, auditor, clock, `FlagCatalog`) and, when `definition_store` is
set, `FlagEditor` (authorizer, definition store, state store, auditor, clock, `FlagCatalog`). See
[toggles-and-edits.md](toggles-and-edits.md).

### `admin`

| Key | Type | Default | Effect |
|---|---|---|---|
| `admin` | boolean or map (`canBeEnabled`) | disabled | Registers `FlagAdminController` (public, `controller.service_arguments`). |
| `admin.actor_provider` | string (service id), non-empty | `Phil\FeatureFlagsBundle\Application\Admin\ActorProvider` | Names the operator of a request. |
| `admin.layout` | string, non-empty | `@PhilFeatureFlags/admin/layout.html.twig` | The Twig template every admin screen extends; it defines the `title` and `content` blocks. |

The controller also takes the `twig` and `router` services. The routes are not loaded automatically: import
`@PhilFeatureFlagsBundle/config/routes/admin.php` ([installation.md](installation.md), [admin.md](admin.md)).

### `clock`

| | |
|---|---|
| Type | string (service id), non-empty |
| Default | `clock` (the PSR-20 clock FrameworkBundle registers when `symfony/clock` is installed) |
| Effect | Given to the database store (`updated_at`), the toggle and editor (audit timestamps), and the admin and the listing (expiry). |

### `flags`

A map of flag name to definition, read by `ConfiguredFlagProvider` (service
`phil_feature_flags.provider.configuration`). Keys are not normalized, so dashes stay dashes.

| Key | Type | Default | Constraints |
|---|---|---|---|
| *(name)* | map, or a boolean | — | Lowercase ASCII segments separated by `.`, `_` or `-`; at most 100 bytes. A boolean `b` is short for `{ active: b }`. |
| `active` | boolean | `false` | A flag is inactive unless declared active. |
| `rules` | list of maps | `[]` | Active without rules, the flag is on for every context; with rules, it is on when any rule matches. |
| `rules[].attribute` | string, required | — | `tenant`, `project` or `subject`. |
| `rules[].members` | list of strings | `[]` | Non-empty identifiers. Exactly one of `members` or `percentage`. |
| `rules[].percentage` | integer or `null` | `null` | 0 to 100. |
| `experiment` | map or `null` | `null` | `attribute` (`tenant`, `project` or `subject`) and `variants`, a map of variant name to whole weight; at least two variants, weights 0 to 100 adding up to 100. |
| `description` | string or `null` | `null` | Trimmed; at most 500 characters; blank means none. |
| `owner` | string or `null` | `null` | Trimmed; at most 100 characters; blank means none. |
| `kind` | string or `null` | `null` | `release`, `experiment`, `ops` or `permission`. |
| `expires_on` | string or `null` | `null` | `YYYY-MM-DD`. An unquoted YAML date (read as a timestamp) is converted back to its UTC date. |

Variant names follow the alphabet of flag names, at most 50 bytes. A flag named `circuit-open.<key>` takes no
rule and no experiment. See [targeting.md](targeting.md), [experiments.md](experiments.md) and
[metadata-and-expiry.md](metadata-and-expiry.md).

For each circuit declared to `phil/circuit-breaker-bundle`, the configuration provider also declares
`circuit-open.<key>`, inactive, without rules, of kind `ops`, with the description `Forces the circuit "<key>"
open.`, unless `flags` declares that name itself. See [circuit-breakers.md](circuit-breakers.md).

## Services registered in every configuration

| Service id | Class | Tags |
|---|---|---|
| `phil_feature_flags.provider.configuration` | `ConfiguredFlagProvider` (factory `fromConfiguration`) | — |
| `phil_feature_flags.context_resolver.anonymous` | `AnonymousContextResolver` | — |
| `Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator` | `FlagEvaluator` | `kernel.reset` (method `reset`) |
| `Phil\FeatureFlagsBundle\Infrastructure\Profiler\FeatureFlagsDataCollector` | `FeatureFlagsDataCollector` | `data_collector` (`id` `phil_feature_flags`) |
| `Phil\FeatureFlagsBundle\Application\Flag\FlagCatalog` | `FlagCatalog` | — |
| `Phil\FeatureFlagsBundle\Infrastructure\Console\ListFlagsCommand` | `ListFlagsCommand` | `console.command` |
| `Phil\FeatureFlagsBundle\Infrastructure\Http\FeatureGateListener` | `FeatureGateListener` | `kernel.event_listener` on `kernel.controller` |
| `Phil\FeatureFlagsBundle\Infrastructure\Routing\FlagRoutingCondition` | `FlagRoutingCondition` | `routing.condition_service` (alias `feature_flags`) |
| `Phil\FeatureFlagsBundle\Infrastructure\Twig\FeatureFlagsExtension` | `FeatureFlagsExtension` | `twig.attribute_extension`, `twig.runtime` |
| `Phil\FeatureFlagsBundle\Application\Circuit\FlagCircuitOverride` | `FlagCircuitOverride` | `phil_circuit_breaker.override` |

Conditional services are listed with their option above; the complete inventory is in
[extension-points.md](extension-points.md#service-ids). All services are private except `FlagAdminController`;
the class-named ones can be autowired by type.

## Build-time validation

Every flag is parsed while the container is built, so a malformed entry fails `cache:clear` rather
than the first request. Errors are `InvalidConfigurationException`s:

| Message (shape) | Cause |
|---|---|
| `phil_feature_flags.flags.<name>: <reason>` | A malformed flag, rule, experiment or metadata, e.g. `phil_feature_flags.flags.new-checkout: A percentage is between 0 and 100; got 120.` |
| `phil_feature_flags.flags.<name>: "<name>" is not a flag name: …` | A name outside the alphabet, or longer than 100 bytes. |
| `phil_feature_flags.flags.circuit-open.<key>: A flag that forces a circuit open takes no rule and no experiment: …` | Rules or an experiment on a force-open flag. |
| `phil_feature_flags.admin needs the toggle: every change it makes is authorized and audited there.` | `admin` enabled without `toggle`. |
| `phil_feature_flags.toggle needs a state_store: a toggle writes to the store the evaluator reads.` | `toggle` enabled with neither `state_store` nor `doctrine`. |
| `phil_feature_flags.<path> must be a service id.` | A service id option given as a non-string (an integer, a boolean), e.g. `phil_feature_flags.toggle.authorizer must be a service id.` |
| `phil_feature_flags.providers must be a list of service ids.` | A non-string entry in `providers`. |

The Symfony configuration tree adds its own errors before these (a missing `attribute` in a rule, a
non-integer `percentage`, an empty string where `cannotBeEmpty()` applies).
A service id that does not exist is reported by the container compiler, not by the bundle.
