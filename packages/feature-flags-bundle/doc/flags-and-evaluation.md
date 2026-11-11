# Flags and evaluation

A flag withdraws a **new** feature without a deploy. Its set grows with the features, and each flag is removed
once its feature is on for everyone. A flag is **not an operational kill switch** for an established subsystem:
such a switch is a short, enumerated list that must keep working when the database or the cache is the problem,
so it is read without going through the providers of this bundle.

## Vocabulary

| Term | Type | Meaning |
|---|---|---|
| Flag name | `FlagName` | Lowercase ASCII segments separated by `.`, `_` or `-`, at most 100 bytes (`new-checkout`, `search.v2`). The name salts the stable buckets, so renaming a flag reshuffles its rollout. |
| Definition | `FlagDefinition` | A name, an active state, a list of rules, an optional experiment and metadata. |
| Context | `EvaluationContext` | The `tenant`, `project` and `subject` of the current request, each an opaque identifier or `null`. |
| Evaluation | `Evaluation` | The outcome for one flag and one context: `enabled`, a `reason`, an `errorCode` when the reason is `ERROR`, and a `variant` when an experiment placed the context. |
| Provider | `FlagProvider` | A source of definitions: the configuration, the database store, a remote service. |
| State store | `FlagStateSource` | A source of active states only, read over the providers' definitions. |

## How a definition evaluates

`FlagDefinition::evaluate()` applies these steps in order:

1. **Inactive wins.** An inactive flag is off for every context, reason `DISABLED`, whatever its rules say. Its
   rules stay in place, so deactivation is a rollback and reactivation restores the exact rollout.
2. **Rules.** Active with no rule, the flag is on with reason `STATIC`. Active with rules, the first rule that
   matches gives the reason (`TARGETING_MATCH` for a membership rule, `SPLIT` for a percentage rollout); when no
   rule matches the flag is off with reason `DEFAULT`. See [targeting.md](targeting.md).
3. **Experiment.** When the flag is on and has an experiment, the experiment places the context in a variant:
   on, reason `SPLIT`, with the variant; or, when the context lacks the experiment's attribute, off with reason
   `DEFAULT`. See [experiments.md](experiments.md).

The metadata (description, owner, kind, expiry) changes nothing in an evaluation.

## Reasons

The reasons and the error code follow the OpenFeature specification's vocabulary (`EvaluationReason`,
`EvaluationErrorCode`):

| Reason | Enabled | When |
|---|---|---|
| `STATIC` | yes | Active, no rule, no experiment. |
| `TARGETING_MATCH` | yes | Active and a membership rule matched (no experiment). |
| `SPLIT` | yes | Active and a percentage rollout admitted the context (no experiment), or the experiment placed the context in a variant. |
| `DEFAULT` | no | Active with rules and none matched, or the experiment could not place the context. |
| `DISABLED` | no | Inactive. |
| `ERROR` | no | Not evaluated; the error code says why (table below). |

| Error code | When |
|---|---|
| `FLAG_NOT_FOUND` | No provider knows the flag. |
| `PARSE_ERROR` | A provider threw `InvalidValue`: it holds the flag, but its definition no longer reads as one. |
| `GENERAL` | A provider or the state store threw any other exception, such as a database that cannot be reached. |

`Evaluation` enforces its own invariants: an error code exactly when the reason is `ERROR`, an `ERROR` evaluation
is off, and only an evaluation that is on carries a variant.

The names are OpenFeature's so that a log line, the profiler panel or a dashboard reads the same as in any tool
built on that specification; the bundle ships no OpenFeature provider and does not depend on its SDK. The
OpenFeature reasons a remote or cached provider would give (`CACHED`, `STALE`, `UNKNOWN`) and the error codes of
typed or remote evaluation (`TYPE_MISMATCH`, `PROVIDER_NOT_READY`, `TARGETING_KEY_MISSING`, `INVALID_CONTEXT`,
`PROVIDER_FATAL`) are not used: a flag is a boolean, evaluated in the process, and a context without the attribute
a rule reads simply does not match it.

### An evaluation never throws

`FlagEvaluator::evaluate()`, `isEnabled()` and `variant()` never throw because a source failed. A provider or the
state store that throws an exception gives an evaluation that is off, reason `ERROR`, code `PARSE_ERROR` for an
`InvalidValue` and `GENERAL` for anything else; the failure is logged as an error on the `feature_flags` channel,
with the flag, the code, the message and the exception, and memoized like any evaluation, so a request asks the
failing source once. A PHP `Error` is a bug rather than a failure of a source and is not caught.
`FlagEvaluator::definition()`, read by the catalog and the admin, still throws: those screens show the failure.

## The evaluator

`FlagEvaluator` is the service the application asks:

```php
use Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;

final readonly class CheckoutMailer
{
    public function __construct(private FlagEvaluator $flags) {}

    public function includeOrderCancellationInEmail(): bool
    {
        return $this->flags->isEnabled(FlagName::of('order-cancellation-in-email'));
    }
}
```

| Method | Returns |
|---|---|
| `isEnabled(FlagName $flag): bool` | Whether the flag is on for the current context. |
| `variant(FlagName $flag): ?VariantName` | The variant of the flag's experiment, or `null` when the flag is off or has no experiment. |
| `evaluate(FlagName $flag): Evaluation` | The full outcome, with its reason. |
| `definition(FlagName): ?FlagDefinition` | The definition the first provider that knows the flag declares, before any stored state; reads no context. |
| `evaluated(): array<string, Evaluation>` | The flags evaluated during the current request, by name, each with its latest evaluation, without the context. |
| `reset(): void` | Ends the request: forgets every memoized evaluation. |

### Once per request and context

The context is resolved on **every** evaluation (one call to `EvaluationContextResolver::current()`), and each
flag is evaluated once per context and memoized. A flag toggled in the middle of a request does not change value
halfway through it, so a page never renders half of a feature, and the controller attribute, Twig and the core
read the same answer for the same context.

**A context that changes during the request gets its own evaluations.** A flag read before the firewall has
loaded the user — by an early listener, for instance — is evaluated for the anonymous context. Once the user is
known, the resolver returns the user's context and the flag is evaluated again for it: an evaluation made before
authentication never answers for the authenticated user. The resolver is therefore called on each evaluation and
must stay cheap ([extension-points.md](extension-points.md#evaluationcontextresolver)).

### Reset in workers

`FlagEvaluator` is tagged `kernel.reset`, so Symfony calls `reset()` between two main requests of a long-running
runtime (FrankenPHP worker mode, RoadRunner) and between two messages of a Messenger worker (the
`services_resetter`). The database stores are tagged too ([database-store.md](database-store.md)). A custom loop
that handles several units of work outside these mechanisms calls `reset()` itself, or injects
`services_resetter`.

In a message consumer, the context resolver reads the message, not a request: the application decides from which
field of the message the tenant, the project and the subject come.

### Providers and precedence

Providers are asked in their configured order and **the first one that knows the flag gives its definition**,
rules included; a later provider that knows the same name is shadowed. A provider that does not know a flag
returns `null` rather than an inactive definition, which would hide the next provider's. **A flag no provider
knows is off**, reason `ERROR`, code `FLAG_NOT_FOUND`. A provider that throws stops the search: the flag is off,
reason `ERROR`, code `PARSE_ERROR` or `GENERAL`, rather than answered by a later provider it might shadow.

The default order is the configuration, then the database store: a flag declared in code wins over a flag of the
same name in the database (and the editor refuses to create a name another provider declares).

### The state store

The active state has its own source. When a state store is configured, its state for a flag **replaces** the
active state of the provider's definition, and the rules, experiment and metadata stay the provider's:

| Provider declares | Store holds | Evaluated as |
|---|---|---|
| active, 20 % by tenant | nothing | active, 20 % by tenant |
| active, 20 % by tenant | `false` | inactive (`DISABLED`) |
| active, 20 % by tenant | `false`, then `true` | active, 20 % by tenant again |
| inactive | `true` | active with the provider's rules |
| nothing | `true` | not found (`ERROR`): a state alone declares no rule |

This is what lets an operator turn off a flag declared in YAML without a deploy, and turn it back on with its
exact rollout.

## The context

`EvaluationContext::of(?string $tenant = null, ?string $project = null, ?string $subject = null)` builds a
context; `EvaluationContext::anonymous()` has no attribute. A missing attribute is `null`; an empty string is
refused, so a rule can tell "absent" from "the tenant whose identifier is empty".

The context carries **identifiers only**, never a name or an address: it reaches every provider. **Do not log an
evaluation with its context**: `subject` identifies a person, and a log of which person saw which feature is a
record about that person. `FlagEvaluator::evaluated()` deliberately keeps no context.

## The catalog

`FlagCatalog` reads the current configuration without evaluating anything and without a context: each flag with
the provider that declares it (by service id), the state the store holds for it, and the definition the
evaluator applies, on the same precedence. It is what the listing command and the admin show.

| Method | Returns |
|---|---|
| `entries(): list<CatalogEntry>` | Every flag the **enumerable** providers know and can read, first declaration wins, sorted by name. A provider that only implements `FlagProvider` contributes none. |
| `unreadable(): list<UnreadableEntry>` | Every flag a `DiagnosableFlagProvider` holds but cannot read, in its place in the precedence, sorted by name. |
| `lookup(FlagName $flag): CatalogEntry\|UnreadableEntry\|null` | The entry for one flag from any provider, an `UnreadableEntry` when its declaring provider throws `InvalidValue`, or `null` when no provider knows it. |
| `find(FlagName $flag): ?CatalogEntry` | The entry for one flag from any provider, or `null`; throws `InvalidValue` when its declaring provider cannot read it. |

`CatalogEntry` has `declared` (the provider's definition), `source` (the provider's service id), `stored`
(`?bool`) and `effective()` (the declared definition with the stored state applied). `UnreadableEntry` has `name`
(as the provider stores it), `reason` and `source`.

**Absent and unreadable are told apart**, as the evaluator tells `FLAG_NOT_FOUND` from `PARSE_ERROR`. An unreadable
flag takes the place the evaluator gives it: a flag an earlier provider declares hides a later unreadable one, and an
unreadable flag hides a later provider's definition of the same name, since the evaluator stops at the provider
that throws.
