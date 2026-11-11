# Circuit breakers

The circuit breakers are `phil/circuit-breaker` and its bundle, `phil/circuit-breaker-bundle`: the state machine,
the policies, the store of states, the HTTP clients behind their circuits and the profiler panel are theirs. This
bundle adds one thing: **a flag forces a circuit open**, through the same audited toggle as any flag.

## The force-open flag of each circuit

Each circuit declared to `phil_circuit_breaker` has a flag, `circuit-open.<key>`
(`FlagCircuitOverride::flagFor($key)`):

```yaml
phil_circuit_breaker:
    circuits:
        http.payments:
            failure_rate_percent: 50
            minimum_calls: 10
            window_seconds: 60
            open_seconds: 30
            http_client: payments.client
```

declares `circuit-open.http.payments` in the configuration provider, **inactive, without rules, of kind `ops`**, with
the description `Forces the circuit "http.payments" open.`, unless `phil_feature_flags.flags` declares it. The keys
reach the provider through `ForceOpenFlagsPass`, once both bundles have loaded their configuration. A circuit key is
at most 87 bytes, so `circuit-open.<key>` is always a valid flag name of at most 100 bytes.

## Forcing a circuit open

`FlagCircuitOverride` is the `CircuitOverride` of `phil/circuit-breaker`, registered by this bundle with the tag
`phil_circuit_breaker.override`: the circuit-breaker bundle asks it on every call to a breaker. It reports a circuit
forced open while the circuit's flag is on. A forced circuit:

- is unavailable whatever its counters say, and its state reads `open`;
- records nothing, so its counters resume where they were when the override is lifted;
- refuses the requests of its HTTP client before they leave, with `CircuitOpen`;
- stays confined to its own key: it never stops a whole subsystem.

Forcing it open is a **toggle** of `circuit-open.<key>` — authorized, with a reason, audited — from the admin or
`FlagToggler` (which needs `toggle` and a state store). The authorizer can require a stronger confirmation for these
flags ([extension-points.md](extension-points.md#toggleauthorizer)). A force-open flag takes no rule and no
experiment: a circuit is forced open for every request or for none.

The override reads the flag through `FlagEvaluator`, so it is evaluated once per request like any flag: a forced
circuit takes effect from the next request (or message). A flag no provider declares is off, so a circuit whose flag
is missing is never forced open.

Declaring `circuit-open.<key>: true` in `phil_feature_flags.flags` forces the circuit open by deploy instead.

## Without the circuit-breaker bundle

`PhilCircuitBreakerBundle` registered, its circuits get their flags and the override is asked. Not registered, no
circuit is declared, no force-open flag is added, and the override is never asked; the flags work alike.
