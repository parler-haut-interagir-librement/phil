# Extension points

The library defines one port, which it ships no implementation of, and one interface for the rules of verification,
which it implements seventeen times. `phil/api-contract-bundle` brings a cache-backed `ObservedDeprecations` to Symfony
applications, and gives every service implementing `OpenApiRule` to its verifier. The idempotency store is a port of
`phil/idempotency`.

| Port | Side | Used by |
|---|---|---|
| `Phil\ApiContract\Deprecation\ObservedDeprecations` | Client | Your log, profiler panel and deprecations command. |
| `Phil\ApiContract\Contract\OpenApiRule` | Server | `ContractVerifier`, beside the built-in rules. |

## `ObservedDeprecations`

Where a client keeps the deprecation signals it has received, endpoint by endpoint. The adapter (a cache pool, a
table, a file) belongs to the application, and so does the meaning of "endpoint": a route name, or a method and
path template, **never a URL with identifiers in it**.

### Methods

| Method | Contract |
|---|---|
| `record(string $endpoint, DeprecationSignal $signal, DateTimeImmutable $observedAt): bool` | Records the latest signal seen on an endpoint. Returns `true` when nothing had been recorded for that endpoint since `$observedAt` minus one day: that is when the caller logs its warning, so the log warns once per endpoint and per day. |
| `all(): array` | `array<string, DeprecationSignal>`, the latest signal per endpoint, for the profiler panel and the verification command. |

### Invariants

- `record()` always stores the signal it is given, even when it returns `false`: the latest signal wins.
- The one-day window is measured from the last recording for that endpoint, not from midnight.

### Example adapter (PSR-6 cache)

```php
use Phil\ApiContract\Deprecation\{DeprecationSignal, ObservedDeprecations};
use Psr\Cache\CacheItemPoolInterface;

final readonly class CacheObservedDeprecations implements ObservedDeprecations
{
    private const string INDEX = 'observed_deprecations';

    public function __construct(private CacheItemPoolInterface $cache) {}

    public function record(string $endpoint, DeprecationSignal $signal, DateTimeImmutable $observedAt): bool
    {
        $item = $this->cache->getItem(self::INDEX);
        /** @var array<string, array{signal: DeprecationSignal, at: DateTimeImmutable}> $seen */
        $seen = $item->isHit() ? $item->get() : [];

        $previous = $seen[$endpoint]['at'] ?? null;
        $seen[$endpoint] = ['signal' => $signal, 'at' => $observedAt];
        $this->cache->save($item->set($seen));

        return null === $previous || $previous <= $observedAt->modify('-1 day');
    }

    public function all(): array
    {
        $item = $this->cache->getItem(self::INDEX);

        return array_map(static fn (array $entry): DeprecationSignal => $entry['signal'], $item->isHit() ? $item->get() : []);
    }
}
```

A verification command then fails while any recorded signal carries a sunset:

```php
$sunset = array_filter($observed->all(), static fn (DeprecationSignal $s): bool => null !== $s->sunsetAt);

return [] === $sunset ? Command::SUCCESS : Command::FAILURE;
```

## `OpenApiRule`

One rule of the verification of an OpenAPI document: a rule of the application's own house style, or one its API
needs. It is given to `ContractVerifier::withBuiltInRules($rule)`, or to `new ContractVerifier([...])` beside the rules
the application keeps.

### Methods

| Method | Contract |
|---|---|
| `id(): string` | 1 to 64 lowercase letters, digits and hyphens, starting with a letter; unique among the verifier's rules. |
| `level(): RuleLevel` | The lowest level whose verification runs the rule. |
| `severity(): Severity` | The severity of every finding the rule yields. |
| `description(): string` | One sentence: what the rule asks of the document. |
| `check(OpenApiDocument $document): iterable` | A `Finding` for each place that breaks the rule, in the document's order; keys are ignored. |

### Invariants

- A rule keeps no state between two documents: the verifier runs it for every document, from any request or worker.
- A rule reads `$document->data` as untrusted: any value may be missing or of any type.
- `Finding::of($this, $location, $message)` takes the rule's id and severity, so that a finding cannot contradict its
  rule.

### Example rule

```php
use Phil\ApiContract\Contract\OpenApiRule;
use Phil\ApiContract\OpenApi\{Finding, OpenApiDocument, RuleLevel, Severity};

final readonly class PluralCollections implements OpenApiRule
{
    public function id(): string { return 'plural-collections'; }
    public function level(): RuleLevel { return RuleLevel::Strict; }
    public function severity(): Severity { return Severity::Warning; }
    public function description(): string { return 'The first segment of every path is a plural noun.'; }

    public function check(OpenApiDocument $document): iterable
    {
        foreach ($document->operations() as $operation) {
            $first = explode('/', $operation->path)[1] ?? '';

            if ('' !== $first && !str_ends_with($first, 's')) {
                yield Finding::of($this, $operation->pointer, sprintf('"%s" is not plural.', $first));
            }
        }
    }
}
```
