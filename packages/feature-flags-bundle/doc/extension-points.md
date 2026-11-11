# Extension points

The bundle carries what its design decides and leaves to the application, through ports (interfaces), what only
the application can decide: the context of a request, the permission and audit trail of a change, the operator of
the admin, where exposures are counted. This page gives each port's contract, the
invariants an adapter must keep, and a short example. It ends with every service id, tag, route, Twig function and
attribute the bundle declares.

Register an adapter as a service and name it in the configuration (or let autowiring alias the interface to the
single implementation, which is what the defaults of `context_resolver`, `toggle.*` and `admin.actor_provider`
expect).

| Port | Namespace | Required when |
|---|---|---|
| `EvaluationContextResolver` | `Application\Flag` | Always, unless `context_resolver` is the anonymous resolver. |
| `FlagProvider` | `Domain\Flag` | Optional: a provider beyond the configuration and the database. |
| `EnumerableFlagProvider` | `Domain\Flag` | Optional: a provider whose flags appear in the listing and the admin. |
| `FlagStateSource` | `Domain\Flag` | Optional: a read-only state store. |
| `WritableFlagStore` | `Application\Toggle` | `toggle` without the database store. |
| `WritableFlagDefinitions` | `Application\Edit` | Creation and editing without the database store. |
| `ToggleAuthorizer` | `Application\Toggle` | `toggle`. |
| `ToggleAuditor` | `Application\Toggle` | `toggle`. |
| `ActorProvider` | `Application\Admin` | `admin`. |
| `ExposureRecorder` | `Application\Flag` | Optional: counting A/B exposures. |
| `Rule` | `Domain\Flag\Rule` | Optional: targeting rules of a custom provider. |

All namespaces are under `Phil\FeatureFlagsBundle\`.

## EvaluationContextResolver

```php
interface EvaluationContextResolver
{
    public function current(): EvaluationContext;
}
```

Builds the context of the current request: from the security token, the active tenant, the project the request
is for. Called **on every evaluation**, so that a context that changes during the request — the firewall loading
the user after an early read — gets evaluations of its own.

Invariants:

- cheap: it reads the token and the request, and memoizes any lookup it needs (a tenant loaded from the database)
  keyed by what it reads;

- identifiers only (`EvaluationContext::of(tenant:, project:, subject:)`), `null` for an absent attribute, never an
  empty string (refused);
- never throws for an anonymous request: return a context with `null`s, and targeted flags are off;
- in a message consumer, reads the message's fields, not a request that does not exist.

```php
use Phil\FeatureFlagsBundle\Application\Flag\EvaluationContextResolver;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class RequestContextResolver implements EvaluationContextResolver
{
    public function __construct(private Security $security, private CurrentTenant $tenant) {}

    public function current(): EvaluationContext
    {
        return EvaluationContext::of(
            tenant: $this->tenant->id(),                                   // ?string
            subject: $this->security->getUser()?->getUserIdentifier(),
        );
    }
}
```

The bundle ships `AnonymousContextResolver` (service `phil_feature_flags.context_resolver.anonymous`), which always
returns `EvaluationContext::anonymous()`.

## FlagProvider

```php
interface FlagProvider
{
    public function find(FlagName $name): ?FlagDefinition;
}
```

A source of definitions. Invariants:

- returns `null` for a flag it does not know, so that the next provider answers; never an invented inactive
  definition, which would hide the next provider's;
- returns a definition whose `name` is the name asked;
- throws `InvalidValue` for a flag it holds whose definition no longer reads as one (the evaluation is then
  `PARSE_ERROR`), and lets any other failure of its source surface as an exception (the evaluation is then
  `GENERAL`); it never answers `null` for a flag it could not read, which would let a later provider answer for it;
- is cheap to call repeatedly within a request (the evaluator memoizes per flag, the catalog does not): cache per
  request and tag the service `kernel.reset` if it reads remote data.

Add it to `providers` in the position that gives it its precedence.

## EnumerableFlagProvider

```php
interface EnumerableFlagProvider extends FlagProvider
{
    /** @return list<FlagDefinition> */
    public function all(): array;
}
```

A provider that can list every flag it knows: its flags then appear in the listing command and the admin list.
`all()` must agree with `find()`. A remote service that only answers one flag at a time stays a plain
`FlagProvider`.

## DiagnosableFlagProvider

```php
interface DiagnosableFlagProvider extends EnumerableFlagProvider
{
    /** @return list<UnreadableFlag> by name */
    public function unreadable(): array;
}
```

An enumerable provider that can also name the flags it holds but cannot read, such as a table whose rows can be
edited by hand. For such a flag, `find()` throws `InvalidValue` (the evaluation is `PARSE_ERROR`), `all()` leaves it
out, and `unreadable()` returns an `UnreadableFlag` with the name as stored and the reason. The catalog then lists
the flag apart, in its place in the precedence, and the listing command and the admin show it with its reason
instead of hiding it. `DoctrineFlagDefinitions` implements it.

```php
use Phil\FeatureFlagsBundle\Domain\Flag\EnumerableFlagProvider;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\FlagDefinitionFormat;

/** Flags shipped in a JSON file, in the configuration's shape. */
final class JsonFileFlags implements EnumerableFlagProvider
{
    /** @var array<string, FlagDefinition>|null */
    private ?array $flags = null;

    public function __construct(private readonly string $path) {}

    public function find(FlagName $name): ?FlagDefinition
    {
        return $this->load()[$name->value] ?? null;
    }

    public function all(): array
    {
        return array_values($this->load());
    }

    /** @return array<string, FlagDefinition> */
    private function load(): array
    {
        if (null === $this->flags) {
            $this->flags = [];
            foreach (json_decode((string) file_get_contents($this->path), true, 16, \JSON_THROW_ON_ERROR) as $name => $flag) {
                $this->flags[$name] = FlagDefinitionFormat::read(FlagName::of($name), $flag);
            }
        }

        return $this->flags;
    }
}
```

## FlagStateSource

```php
interface FlagStateSource
{
    public function isActive(FlagName $flag): ?bool;
}
```

The active state of a flag when it can change without a deploy. Invariants:

- holds the active state only, never rules: the state replaces the provider's `active`, the rules stay the
  provider's;
- returns `null` for a flag it does not hold, so the provider's state applies;
- a state for a flag no provider declares is ignored by the evaluator.

Name it as `state_store`. Read-only sources are fine without `toggle`.

## WritableFlagStore

```php
interface WritableFlagStore extends FlagStateSource
{
    public function compareAndSet(FlagName $flag, bool $expected, bool $active): bool;
    public function insert(FlagName $flag, bool $active): bool;
}
```

What a toggle writes to (and the editor, for the initial state of a created flag). Invariants:

- `compareAndSet()` sets the state **only if it is still `$expected`**, atomically (a conditional `UPDATE`, a
  compare-and-set), and returns `false` when the current state is no longer `$expected` or the flag is not held;
- `insert()` stores the state of a flag the store does not hold yet, **only if it still does not**, atomically, and
  returns `false` when the flag is already held;
- after a write, `isActive()` in the same process reflects it (clear any per-request cache on write);
- throw only on infrastructure failure: a lost race is a `false`, never an exception.

```php
use Phil\FeatureFlagsBundle\Application\Toggle\WritableFlagStore;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;

/** Active states in Redis, with atomic primitives. */
final readonly class RedisFlagStore implements WritableFlagStore
{
    private const string LUA_CAS = <<<'LUA'
        if redis.call('HGET', KEYS[1], ARGV[1]) == ARGV[2] then redis.call('HSET', KEYS[1], ARGV[1], ARGV[3]) return 1 end
        return 0
        LUA;

    public function __construct(private \Redis $redis) {}

    public function isActive(FlagName $flag): ?bool
    {
        $value = $this->redis->hGet('flags', $flag->value);

        return false === $value ? null : '1' === $value;
    }

    public function compareAndSet(FlagName $flag, bool $expected, bool $active): bool
    {
        return 1 === $this->redis->eval(self::LUA_CAS, ['flags', $flag->value, $expected ? '1' : '0', $active ? '1' : '0'], 1);
    }

    public function insert(FlagName $flag, bool $active): bool
    {
        return true === $this->redis->hSetNx('flags', $flag->value, $active ? '1' : '0');
    }
}
```

## WritableFlagDefinitions

```php
interface WritableFlagDefinitions
{
    public function findRevised(FlagName $flag): ?RevisedDefinition;
    public function create(FlagDefinition $definition): bool;
    public function replace(FlagDefinition $definition, int $revision): bool;
}
```

The definitions an admin creates and edits, usually also one of the evaluator's providers (and then it should be
listed in `providers`, and is the `definition_store`). Invariants:

- `findRevised()` returns the stored definition with its revision (at least 1), or `null`; it reads the current
  value, not a per-request cache, and may throw `InvalidValue` for a row that no longer reads;
- `create()` stores revision 1 and returns `false` when a flag of that name is already held, atomically;
- `replace()` writes **only if the revision is still `$revision`**, increments it, and returns `false` when it moved
  or the flag is gone, atomically;
- a write clears any per-request cache the provider side keeps.

`DoctrineFlagDefinitions` is the reference implementation ([database-store.md](database-store.md)).

## ToggleAuthorizer

```php
interface ToggleAuthorizer
{
    /** @throws ToggleRefused */
    public function authorize(ToggleRequest $request): void;

    /** @throws ToggleRefused */
    public function authorizeEdit(EditRequest $request): void;
}
```

Decides whether an actor may change a flag. Called first, before anything is read. Invariants:

- returns normally to allow; throws `ToggleRefused::forFlag($flag, $why)` to refuse (`$why` is shown to the
  operator in the admin);
- has no side effect: a refused change writes and audits nothing.

`ToggleRequest` carries `flag`, `active` (the state asked), `actor`, `reason`; `EditRequest` carries `definition`,
`actor`, `reason`, `revision` (`null` for a creation), with `flag()` and `isCreation()`.

```php
use Phil\FeatureFlagsBundle\Application\Toggle\ToggleAuthorizer;
use Phil\FeatureFlagsBundle\Application\Circuit\FlagCircuitOverride;
use Phil\FeatureFlagsBundle\Domain\Edit\EditRequest;
use Phil\FeatureFlagsBundle\Domain\Exception\ToggleRefused;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleRequest;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class PermissionAuthorizer implements ToggleAuthorizer
{
    public function __construct(private Security $security) {}

    public function authorize(ToggleRequest $request): void
    {
        if (!$this->security->isGranted('ROLE_FLAG_OPERATOR')) {
            throw ToggleRefused::forFlag($request->flag, 'the operator lacks the flag permission.');
        }

        if (str_starts_with($request->flag->value, FlagCircuitOverride::FORCE_OPEN_FLAG_PREFIX)
            && !$this->security->isGranted('ROLE_CIRCUIT_OPERATOR')) {
            throw ToggleRefused::forFlag($request->flag, 'forcing a circuit needs the circuit permission.');
        }
    }

    public function authorizeEdit(EditRequest $request): void
    {
        if (!$this->security->isGranted('ROLE_FLAG_EDITOR')) {
            throw ToggleRefused::forFlag($request->flag(), 'the operator lacks the edit permission.');
        }
    }
}
```

## ToggleAuditor

```php
interface ToggleAuditor
{
    public function record(ToggleRecord $record): void;
    public function recordEdit(EditRecord $record): void;
}
```

Writes the audit line of a change **before** the change is written. Invariants:

- throws when it cannot write the line: the change is then not applied;
- the line may outlive a write that fails afterwards unless the caller runs the change in one transaction shared
  with the store ([toggles-and-edits.md](toggles-and-edits.md#transactions));
- keep what the record carries: the actor, the flag, before and after, the reason, the time.

```php
use Phil\FeatureFlagsBundle\Application\Toggle\ToggleAuditor;
use Phil\FeatureFlagsBundle\Domain\Edit\EditRecord;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleRecord;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\FlagDefinitionFormat;

final readonly class TableAuditor implements ToggleAuditor
{
    public function __construct(private \Doctrine\DBAL\Connection $db) {}

    public function record(ToggleRecord $r): void
    {
        $this->db->insert('flag_audit', [
            'flag' => $r->flag->value, 'actor' => $r->actor, 'reason' => $r->reason->value,
            'change' => json_encode(['active' => [$r->before, $r->after]]), 'at' => $r->at->format(DATE_ATOM),
        ]);
    }

    public function recordEdit(EditRecord $r): void
    {
        $this->db->insert('flag_audit', [
            'flag' => $r->flag->value, 'actor' => $r->actor, 'reason' => $r->reason->value,
            'change' => json_encode([
                'before' => null === $r->before ? null : FlagDefinitionFormat::write($r->before),
                'after' => FlagDefinitionFormat::write($r->after),
            ]),
            'at' => $r->at->format(DATE_ATOM),
        ]);
    }
}
```

## ActorProvider

```php
interface ActorProvider
{
    public function current(): ?string;
}
```

Who is using the admin screens: the application's opaque identifier of the operator, from its own authentication,
or `null` when nobody is authenticated (every screen then answers 403). The identifier goes into audit lines and
must not be blank. Example in [admin.md](admin.md#the-operator-and-the-firewall).

## ExposureRecorder

```php
interface ExposureRecorder
{
    public function record(FlagName $flag, VariantName $variant): void;
}
```

Told that a variant was shown: once per flag and context per request, on the first evaluation that yields a variant.
Invariants:

- receives the flag and the variant, nothing else, and must not try to attach a context: an exposure is a count
  added to an aggregate, never a record of who saw what;
- is called inside the evaluation: keep it fast, and do not let an infrastructure failure break the page (catch and
  log, or buffer and flush later).

Example in [experiments.md](experiments.md#exposures).

## Rule

```php
interface Rule
{
    public function matches(FlagName $flag, EvaluationContext $context): bool;
    public function matchReason(): EvaluationReason;
    public function reads(): TargetingAttribute;
}
```

A targeting rule. Invariants: a rule that cannot read the attribute it needs does not match (fail closed);
`reads()` names the one attribute of the context it reads, which is how a route condition tells a flag it can
evaluate before authentication from one it cannot;
`matches()` is deterministic for a flag and a context; `matchReason()` is `TARGETING_MATCH` for a rule that picks
contexts out and `SPLIT` for one that admits a share. Custom rules work in definitions built by the application's
own providers, but cannot be written by `FlagDefinitionFormat` (the configuration, the database store and the admin
know only `MemberOf` and `PercentageRollout`).

## Service ids

| Service id | Class | Registered | Tags |
|---|---|---|---|
| `phil_feature_flags.provider.configuration` | `Infrastructure\Configuration\ConfiguredFlagProvider` | always | — |
| `phil_feature_flags.context_resolver.anonymous` | `Application\Flag\AnonymousContextResolver` | always | — |
| `Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator` | `FlagEvaluator` | always | `kernel.reset` |
| `Phil\FeatureFlagsBundle\Application\Flag\FlagCatalog` | `FlagCatalog` | always | — |
| `Phil\FeatureFlagsBundle\Infrastructure\Console\ListFlagsCommand` | `ListFlagsCommand` | always | `console.command` |
| `Phil\FeatureFlagsBundle\Infrastructure\Http\FeatureGateListener` | `FeatureGateListener` | always | `kernel.event_listener` (`kernel.controller`) |
| `Phil\FeatureFlagsBundle\Infrastructure\Routing\FlagRoutingCondition` | `FlagRoutingCondition` | always | `routing.condition_service` (alias `feature_flags`) |
| `Phil\FeatureFlagsBundle\Infrastructure\Twig\FeatureFlagsExtension` | `FeatureFlagsExtension` | always | `twig.attribute_extension`, `twig.runtime` |
| `phil_feature_flags.provider.doctrine` | `Infrastructure\Doctrine\DoctrineFlagDefinitions` | `doctrine` | `kernel.reset` |
| `phil_feature_flags.state_store.doctrine` | `Infrastructure\Doctrine\DoctrineFlagStore` | `doctrine` | `kernel.reset` |
| `Phil\FeatureFlagsBundle\Infrastructure\Doctrine\FlagSchemaListener` | `FlagSchemaListener` | `doctrine` | `doctrine.event_listener` (`postGenerateSchema`, the connection) |
| `Phil\FeatureFlagsBundle\Application\Toggle\FlagToggler` | `FlagToggler` | `toggle` | — |
| `Phil\FeatureFlagsBundle\Application\Edit\FlagEditor` | `FlagEditor` | `toggle` and a definition store | — |
| `Phil\FeatureFlagsBundle\Infrastructure\Http\FlagAdminController` | `FlagAdminController` | `admin` | `controller.service_arguments`; public |
| `Phil\FeatureFlagsBundle\Application\Circuit\FlagCircuitOverride` | `FlagCircuitOverride` | always | `phil_circuit_breaker.override` |

The string ids are also constants of `PhilFeatureFlagsBundle`: `CONFIGURATION_PROVIDER`, `DOCTRINE_PROVIDER`,
`DOCTRINE_STATE_STORE`, `ANONYMOUS_CONTEXT_RESOLVER`; `DEFAULT_LAYOUT` is the default admin layout. Every service is
private except the admin controller; inject them by type (the class-named ones autowire) or by id.

## Routes

| Name | Path (under the import prefix) | Methods |
|---|---|---|
| `phil_feature_flags_admin_list` | `/` | GET |
| `phil_feature_flags_admin_create` | `/_new` | GET, POST |
| `phil_feature_flags_admin_show` | `/{name}` | GET |
| `phil_feature_flags_admin_toggle` | `/{name}/toggle` | POST |
| `phil_feature_flags_admin_edit` | `/{name}/edit` | GET, POST |

Loaded from `@PhilFeatureFlagsBundle/config/routes/admin.php` ([admin.md](admin.md)).

## Twig functions

| Function | Returns |
|---|---|
| `feature_enabled(string flag)` | `bool` |
| `feature_variant(string flag)` | `?string` |

## Routing condition service

`service('feature_flags')` in a route condition, with `enabled(string $flag): bool` and
`variant(string $flag): ?string`. A flag with a rule or an experiment on the subject or the tenant throws
`Infrastructure\Routing\FlagNotRoutable` ([placement.md](placement.md#in-routing-the-route-condition)).

## Attribute

`#[Phil\FeatureFlagsBundle\Infrastructure\Http\IsFeatureEnabled(string $flag, ?string $variant = null)]`, on a
controller class or method, repeatable ([placement.md](placement.md)).

## Console command

`phil:feature-flags:list [--expired] [--fail-on-expired]` ([console.md](console.md)).

## Twig namespace

`@PhilFeatureFlags`, holding `admin/layout.html.twig`, `admin/list.html.twig`, `admin/show.html.twig` and
`admin/form.html.twig`.
