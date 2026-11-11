# Toggles and edits

Two services change flags at runtime, both enabled by `toggle` ([configuration.md](configuration.md#toggle)):

- `FlagToggler` sets a flag active or inactive, in the state store;
- `FlagEditor` creates a flag, or changes its rules, experiment and metadata, in the definition store (registered
  only when a `definition_store` exists).

Both follow the same order — **authorize, read, audit, write** — through the same two adapters of the
application, `ToggleAuthorizer` and `ToggleAuditor`, and every change carries a mandatory reason and an actor.

## The reason and the actor

`ToggleReason::of(string $value)`:

- refuses a value that is not valid UTF-8;
- trims every Unicode blank from both ends — white space and space separators, `\s` and `\p{Z}` in Unicode mode,
  so U+00A0 NO-BREAK SPACE and U+3000 IDEOGRAPHIC SPACE included — and refuses a reason left empty;
- refuses a reason longer than 1,000 characters (`ToggleReason::MAX_LENGTH`).

The actor is the application's opaque identifier of the operator (a user id, a service account). `ToggleRequest`
and `EditRequest` refuse an actor that is not valid UTF-8 or that is empty or blank in the same Unicode sense: an
anonymous change cannot be audited. All refusals are `InvalidValue` exceptions.

## Toggling

```php
use Phil\FeatureFlagsBundle\Application\Toggle\FlagToggler;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleReason;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleRequest;

$changed = $toggler->toggle(new ToggleRequest(
    FlagName::of('new-checkout'),
    false,                                         // the state asked for
    'user:42',                                     // the actor
    ToggleReason::of('Checkout errors spiking after release 3.4.'),
));
```

`toggle()` returns whether the flag changed, and runs:

1. **Authorize** — `ToggleAuthorizer::authorize($request)`; a refusal throws `ToggleRefused`, and nothing is read,
   written or audited.
2. **Read** — the store's current state; when the store does not hold the flag yet, the state its provider
   declares (through `FlagCatalog::find()`). A flag no provider declares throws `UnknownFlag`: **a toggle never
   creates a flag**.
3. **No-op** — asking for the value the flag already has returns `false`, writes nothing and audits nothing, so the
   trail only holds changes.
4. **Audit** — `ToggleAuditor::record(ToggleRecord)`, with the flag, the actor, the value before and after, the
   reason and the clock's time. **The audit line comes before the write**: an auditor that throws stops the toggle
   before anything is written, so no toggle exists without its trace.
5. **Write** — a compare-and-set on the value read (`WritableFlagStore::compareAndSet($flag, $before, $active)`),
   or, for a flag the store did not hold, an atomic insert of its first state (`WritableFlagStore::insert()`). A
   write that returns `false` lost a race and throws `ToggleConflict`: two concurrent toggles cannot both apply,
   and the "before" of the audit line is the value the write replaced.

### First toggle of a declared flag

A flag declared in the configuration has no row in the state store until its first toggle. That toggle starts from
the declared state, stores the new state with an insert that the store refuses if another process inserted first,
and from then on the stored state overrides the declared one ([flags-and-evaluation.md](flags-and-evaluation.md#the-state-store)).
The declared rules stay in force.

### Transactions

When the write fails after the audit line was written (a conflict, a database error), the line stays behind unless
the application runs `toggle()` inside one transaction shared by the store and the auditor, which the exception
then rolls back:

```php
$connection->transactional(fn () => $toggler->toggle($request));
```

Without that transaction, the trail can hold a toggle that did not apply, never miss one that did. Whether the
store and the auditor share a connection is the application's decision.

## Creating a flag

```php
use Phil\FeatureFlagsBundle\Application\Edit\FlagEditor;
use Phil\FeatureFlagsBundle\Domain\Edit\EditRequest;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\Percentage;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\PercentageRollout;
use Phil\FeatureFlagsBundle\Domain\Flag\TargetingAttribute;

$editor->create(new EditRequest(
    new FlagDefinition(FlagName::of('beta-search'), false, [
        new PercentageRollout(TargetingAttribute::Tenant, Percentage::of(10)),
    ]),
    'user:42',
    ToggleReason::of('Opening the search beta.'),
));                                                // no revision: a creation
```

`create()` runs:

1. `ToggleAuthorizer::authorizeEdit($request)` (`ToggleRefused` on refusal).
2. A request that names a revision is refused (`InvalidValue`): a creation has nothing to have read.
3. A name **any provider** already declares throws `EditConflict::alreadyDeclared()`: another provider would shadow
   the new flag. A name a provider holds but cannot read is declared too (`FlagCatalog::lookup()`).
4. The definition is forced **inactive** whatever the request says.
5. `ToggleAuditor::recordEdit(EditRecord)` with `before = null`.
6. `WritableFlagDefinitions::create()`; `false` (the name was inserted meanwhile) throws `EditConflict`.
7. `WritableFlagStore::insert($flag, false)` stores the initial state, inactive.

Turning the new flag on is a **toggle**, on its own audit line.

**One exception to "starts inactive":** if the state store still holds a state for that name — left by an earlier
flag of the same name whose definition is gone — the insert is refused and that state is kept, as the state an
operator last set. If that state was `true`, the recreated flag is active as soon as it exists. Clear the state
row when a flag is retired if this matters.

## Editing a flag

```php
$current = $definitions->findRevised(FlagName::of('beta-search'));     // RevisedDefinition: definition + revision

$editor->edit(new EditRequest(
    new FlagDefinition(FlagName::of('beta-search'), true, [
        new PercentageRollout(TargetingAttribute::Tenant, Percentage::of(50)),
    ]),
    'user:42',
    ToggleReason::of('Widening the beta.'),
    $current->revision,                                                // the revision read
));
```

`edit()` runs:

1. `ToggleAuthorizer::authorizeEdit($request)`.
2. A request without a revision is refused (`InvalidValue`).
3. `WritableFlagDefinitions::findRevised()`; a flag the definition store does not hold throws `UnknownFlag`.
4. A revision other than the stored one throws `EditConflict::staleRevision()`: the operator reads the flag again.
5. **The active state is kept**: the request's `active` is ignored and the stored definition's state is used.
   Activation is a toggle.
6. `ToggleAuditor::recordEdit(EditRecord)` with the definitions before and after.
7. `WritableFlagDefinitions::replace($definition, $revision)` writes only if the revision is still the one read and
   increments it; `false` throws `EditConflict`.

Raising a rollout from 20 % to 100 % changes as much as a toggle, so it is traced as one.

## Records

| Record | Fields |
|---|---|
| `ToggleRecord` | `flag` (`FlagName`), `actor` (string), `before` (bool), `after` (bool), `reason` (`ToggleReason`), `at` (`DateTimeImmutable`). |
| `EditRecord` | `flag`, `actor`, `before` (`?FlagDefinition`, `null` for a creation), `after` (`FlagDefinition`), `reason`, `at`. |

`FlagDefinitionFormat::write()` turns a definition into an array in the configuration's shape, which an auditor can
JSON-encode.

## Exceptions

All of them implement `Phil\DddBundle\Domain\Exception\DomainError` except `InvalidValue`, which is the DDD bundle's
validation exception.

| Exception | Thrown when |
|---|---|
| `InvalidValue` | A blank reason or actor, a creation with a revision, an edit without one, a malformed definition. |
| `ToggleRefused` | The authorizer refused (`ToggleRefused::forFlag($flag, $why)`). |
| `UnknownFlag` | A toggle of a flag no provider declares; an edit of a flag the definition store does not hold. |
| `ToggleConflict` | The compare-and-set or the first insert lost a race. |
| `EditConflict` | A stale revision, a lost replace, or a creation of a name already declared. |

The admin maps them to HTTP statuses ([admin.md](admin.md)).

## Authorizing strongly

The authorizer decides which permission each change needs, and which flags need more: forcing a circuit open
(`circuit-open.*`), turning off a kind `ops` flag, or any flag listed by the application can require a stronger
confirmation (a second factor, a hardware key) on top of the permission. The bundle passes the whole request, so
the authorizer can look at the flag name, the target state and the actor ([extension-points.md](extension-points.md#toggleauthorizer)).
