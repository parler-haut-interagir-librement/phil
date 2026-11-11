# Reference

The public API by layer. All classes are under `Phil\FeatureFlagsBundle\`. **State**: *implemented* (tested in
`tests/Unit` or `tests/Functional`), *port* (an interface the application implements or may implement), *wired*
(registered by the bundle from configuration).

`Domain` depends only on `phil/ddd-bundle` (for `InvalidValue` and `DomainError`); `Application` adds `psr/clock`;
`Infrastructure` adds Doctrine DBAL and ORM, the HTTP kernel and routing, the console and Twig.

## Domain\Flag

| Type | Role | State |
|---|---|---|
| `FlagName` | The name of a flag: lowercase ASCII segments separated by `.`, `_`, `-`, at most 100 bytes. | implemented |
| `FlagDefinition` | A flag as a provider knows it: active state, rules, experiment, metadata; `evaluate()`, `withActive()`, `readsAnyOf()`. | implemented |
| `Evaluation` | The outcome for one flag and one context: enabled, reason, error code, variant; `notFound()`, `failed()`. | implemented |
| `EvaluationReason` | `STATIC`, `TARGETING_MATCH`, `SPLIT`, `DEFAULT`, `DISABLED`, `ERROR` (OpenFeature vocabulary). | implemented |
| `EvaluationErrorCode` | `FLAG_NOT_FOUND`, `PARSE_ERROR`, `GENERAL` (OpenFeature vocabulary). | implemented |
| `EvaluationContext` | Tenant, project and subject identifiers, each nullable; `of()`, `anonymous()`, `get()`. | implemented |
| `TargetingAttribute` | `tenant`, `project`, `subject`. | implemented |
| `Bucket` | The stable bucket 0–99: `of()` for rollouts, `forVariant()` for experiments (`murmur3a`). | implemented |
| `Percentage` | A whole percentage 0–100; `admits(Bucket)`. | implemented |
| `Experiment` | Weighted variants on one attribute; `assign()`. | implemented |
| `Variant` | One arm and its weight 0–100. | implemented |
| `VariantName` | The name of a variant, at most 50 bytes. | implemented |
| `FlagMetadata` | Description, owner, kind, expiry date; `isExpiredAt()`. | implemented |
| `FlagKind` | `release`, `experiment`, `ops`, `permission`. | implemented |
| `FlagProvider` | A source of definitions: `find()`. | port |
| `EnumerableFlagProvider` | A provider that can list its flags: `all()`. | port |
| `DiagnosableFlagProvider` | An enumerable provider that also lists the flags it cannot read: `unreadable()`. | port |
| `UnreadableFlag` | A flag a provider holds but cannot read: the name as stored and the reason. | implemented |
| `FlagStateSource` | A source of active states: `isActive()`. | port |

## Domain\Flag\Rule

| Type | Role | State |
|---|---|---|
| `Rule` | A targeting rule: `matches()`, `matchReason()`, `reads()`. | port |
| `MemberOf` | Matches when an attribute is in an explicit set; reason `TARGETING_MATCH`. | implemented |
| `PercentageRollout` | Matches a stable share of an attribute's values; reason `SPLIT`. | implemented |

## Domain\Toggle

| Type | Role | State |
|---|---|---|
| `ToggleReason` | A mandatory reason, Unicode-trimmed, at most 1,000 characters; `BLANK` is the blank character class. | implemented |
| `ToggleRequest` | An actor asking to set a flag active or inactive, with a reason. | implemented |
| `ToggleRecord` | The audit line of an applied toggle. | implemented |

## Domain\Edit

| Type | Role | State |
|---|---|---|
| `EditRequest` | An actor asking to create a flag (no revision) or change one (with the revision read). | implemented |
| `EditRecord` | The audit line of an applied creation or edit, definitions before and after. | implemented |
| `RevisedDefinition` | A stored definition and its revision (at least 1). | implemented |

## Domain\Exception

| Type | Role | State |
|---|---|---|
| `ToggleRefused` | The authorizer refused a change. | implemented |
| `UnknownFlag` | A toggle or edit named a flag that cannot be changed. | implemented |
| `ToggleConflict` | A toggle lost a race. | implemented |
| `EditConflict` | A stale revision, a lost write, or a name already declared. | implemented |

All implement `Phil\DddBundle\Domain\Exception\DomainError`.

## Application\Flag

| Type | Role | State |
|---|---|---|
| `FlagEvaluator` | Evaluates flags once per request and context over ordered providers, a state store and an exposure recorder; `definition()`, `reset()`. | implemented, wired (`kernel.reset`) |
| `FlagCatalog` | The current configuration: `entries()`, `unreadable()`, `lookup()`, `find()`. | implemented, wired |
| `CatalogEntry` | One flag with its declared definition, source and stored state; `effective()`. | implemented |
| `UnreadableEntry` | One flag its declaring provider cannot read: name, reason, source. | implemented |
| `AnonymousContextResolver` | A context without attributes. | implemented, wired |
| `EvaluationContextResolver` | Builds the context of the current request. | port |
| `ExposureRecorder` | Counts A/B exposures by flag and variant. | port |

## Application\Toggle

| Type | Role | State |
|---|---|---|
| `FlagToggler` | The audited toggle: authorize, read, audit, write (compare-and-set or first insert). | implemented, wired with `toggle` |
| `ToggleAuthorizer` | Decides toggles, creations and edits. | port |
| `ToggleAuditor` | Writes the audit line of each change. | port |
| `WritableFlagStore` | A state store a toggle writes to: `compareAndSet()`, `insert()`. | port |

## Application\Edit

| Type | Role | State |
|---|---|---|
| `FlagEditor` | The audited creation and edit, conditional on the revision. | implemented, wired with `toggle` and a definition store |
| `WritableFlagDefinitions` | The definitions the admin writes: `findRevised()`, `create()`, `replace()`. | port |

## Application\Admin

| Type | Role | State |
|---|---|---|
| `ActorProvider` | The operator of the admin screens. | port |

## Application\Circuit

| Type | Role | State |
|---|---|---|
| `FlagCircuitOverride` | The `CircuitOverride` of `phil/circuit-breaker`: forces a circuit open while `circuit-open.<key>` is on; `flagFor()`, `FORCE_OPEN_FLAG_PREFIX`. | implemented, wired and tagged `phil_circuit_breaker.override` |

## Infrastructure\Configuration

| Type | Role | State |
|---|---|---|
| `ConfiguredFlagProvider` | The flags of `phil_feature_flags.flags`, plus each circuit's force-open flag; enumerable. | implemented, wired |
| `FlagDefinitionFormat` | The one array shape of a flag, read from configuration, database rows and admin forms, written back to the last two. | implemented |

## Infrastructure\Doctrine

| Type | Role | State |
|---|---|---|
| `DoctrineFlagDefinitions` | Admin-created flags in `phil_feature_flags_definition`: diagnosable provider (an unreadable row is `PARSE_ERROR` and listed with its reason) and writable definitions. | implemented, tested on SQLite, MySQL and PostgreSQL, wired with `doctrine` |
| `DoctrineFlagStore` | Active states in `phil_feature_flags_state`. | implemented, tested, wired with `doctrine` |
| `FlagSchemaListener` | Adds the tables to the ORM-generated schema. | implemented, tested, wired with `doctrine` |
| `FlagTables` | The table names and `configure(Schema)`. | implemented |

## Infrastructure\Http

| Type | Role | State |
|---|---|---|
| `IsFeatureEnabled` | Controller attribute: 404 while the flag is off or outside the variant. | implemented |
| `FeatureGateListener` | Enforces `IsFeatureEnabled` on `kernel.controller`. | implemented, wired |
| `FlagAdminController` | The admin screens: `list`, `show`, `toggle`, `create`, `edit`. | implemented, wired with `admin` |
| `SameOriginGuard` | Refuses cross-site form posts by `Sec-Fetch-Site` or `Origin`. | implemented |

## Infrastructure\Routing, Twig, Console

| Type | Role | State |
|---|---|---|
| `Routing\FlagRoutingCondition` | `service('feature_flags')` in route conditions: `enabled()`, `variant()`; refuses a flag targeted at subjects or tenants. | implemented, wired |
| `Routing\FlagNotRoutable` | Thrown by a route condition that reads a flag targeted at subjects or tenants. | implemented |
| `Twig\FeatureFlagsExtension` | `feature_enabled()` and `feature_variant()`. | implemented, wired |
| `Console\ListFlagsCommand` | `phil:feature-flags:list`. | implemented, wired |

## Infrastructure\DependencyInjection

| Type | Role | State |
|---|---|---|
| `ForceOpenFlagsPass` | Gives the configuration provider the keys of the circuits of `phil/circuit-breaker-bundle`, so that each declares its force-open flag. | implemented, added by the bundle |

## Bundle

| Type | Role | State |
|---|---|---|
| `PhilFeatureFlagsBundle` | Configuration tree (alias `phil_feature_flags`), build-time validation, wiring; service id constants. | implemented, tested |

## Resources

| Path | Role |
|---|---|
| `config/routes/admin.php` | The five admin routes. |
| `templates/admin/layout.html.twig` | The default layout (`title` and `content` blocks). |
| `templates/admin/list.html.twig`, `show.html.twig`, `form.html.twig` | The admin screens. |
