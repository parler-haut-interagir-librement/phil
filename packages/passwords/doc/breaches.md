# Breach checks

## k-anonymity

`PwnedPasswordsSource` hashes the password with SHA-1, sends the first five hexadecimal characters of the hash to the
range API, and looks for the other 35 in the answer: the service never sees the password nor its hash, only a prefix
shared by hundreds of others. Padding is asked by default (`Add-Padding: true`), so that the size of the answer tells
an observer of the traffic nothing of the prefix. SHA-1 here is the address of the password in the corpus, not a
protection of it: nothing in the library stores a password.

```php
$checker = new BreachChecker(new PwnedPasswordsSource($httpClient));
$check = $checker->check($password);             // threshold 1
$check = $checker->check($password, threshold: 10);
```

| Member of `BreachCheck` | What it holds |
|---|---|
| `status` | `BreachStatus::Compromised`, `NotFound` or `Unavailable`. |
| `occurrences` | The occurrences the source reported, 0 when it could not answer. |
| `failure` | The `BreachCheckUnavailable` of a source that could not answer, null otherwise. |
| `isCompromised()`, `isUnavailable()` | Shortcuts. |

## The source

| Argument of `PwnedPasswordsSource` | Default | Effect |
|---|---|---|
| `client` | — | The HTTP client, from `symfony/http-client-contracts`. |
| `baseUrl` | `https://api.pwnedpasswords.com/range/` | A mirror that serves the same format. |
| `padding` | `true` | Asks for padded answers. |
| `timeout` | 5 seconds | The idle timeout; the whole request is bounded by twice that. |
| `userAgent` | `phil-passwords` | The service refuses requests without one. |
| `maxBytes` | 1 MiB | The largest answer read; a padded range is a few tens of kilobytes. |

## Failures

A source that cannot answer — the network, a status other than 200, a timeout, an answer too large or malformed —
throws `BreachCheckUnavailable`, and `BreachChecker` turns it into the status `Unavailable` with the failure. Whether
to accept the password then is the application's decision: the `NotCompromised` constraint refuses it by default
(failing closed) and accepts it with `skipOnError`. The password is never in the message of the failure.

`BreachCheckUnavailable` is the one exception a circuit breaker around the source counts. A decorator of the port
keeps the source from being called while the circuit is open:

```php
final readonly class GuardedSource implements BreachedPasswordSource
{
    public function __construct(private BreachedPasswordSource $inner, private CircuitBreaker $breaker)
    {
    }

    public function occurrences(#[SensitiveParameter] string $password): int
    {
        if (!$this->breaker->isAvailable()) {
            throw new BreachCheckUnavailable('The breach source is paused.');
        }

        try {
            $occurrences = $this->inner->occurrences($password);
        } catch (BreachCheckUnavailable $failure) {
            $this->breaker->recordFailure();

            throw $failure;
        }

        $this->breaker->recordSuccess();

        return $occurrences;
    }
}
```

## Other sources

`InMemoryBreachSource` holds hashes in memory: `fromPasswords()` for a list of passwords and their occurrences,
`fromHashes()` for SHA-1 hashes, as a list imported for a network without access to the Internet. Any class that
implements `BreachedPasswordSource` is a source; `RangeResponse::occurrences()` reads a range for a source that fetches
it otherwise.
