# Testing

## The bundle's tests

The suite `tests/Unit` tests the token format — generation over the whole alphabet, the checksum, every malformed shape
—, the row and its rules, the hasher, the scope catalogue, `ApiTokens` on a store in memory at a fixed time — creation,
lifetimes, finding, revocation, rotation with and without grace, purge, verification and its throttled last use, key
rotation, leaked tokens —, one contract for both stores, run in memory and on Doctrine with SQLite in memory, the token
handler through Symfony's `access_token` authenticator, the listener of the roles, the problem details, the voter and
the listener of `#[RequiresScope]`, the endpoint of GitHub's secret scanning with a key pair it makes, the integrations,
the four commands, the members' page and its form, and the configuration and wiring. The suite `tests/Functional` boots
an application with FrameworkBundle, SecurityBundle, TwigBundle, DoctrineBundle on SQLite, `phil/api-contract-bundle`,
`phil/rate-limit-bundle` and `phil/usage-meter-bundle`, and follows a token from the members' page to the API — its
scopes, its quota, its revocation —, through a report of GitHub's secret scanning, and through the commands.

From the monorepo root:

```sh
vendor/bin/phpunit packages/api-keys-bundle/tests
```

The bundle is held to 100 % covered mutation score by Infection.

## The fuzz targets

`tests/Fuzz/token.php` gives the reader of tokens text nobody wrote: whatever it reads, it returns a token that writes
back to the same text, or throws `MalformedToken`, and nothing else. `tests/Fuzz/github-leak-report.php` and
`tests/Fuzz/github-public-keys.php` do the same for the readers of GitHub's reports and keys: matches and keys within
their bounds, or `UnreadableReport`.

```sh
composer fuzz -- api-keys-bundle
```

## The benchmark

`tests/Benchmark/AuthenticatorBench.php` measures what every API request pays: reading a token, the keyed hash, a
verification on a store in memory with its last use throttled, and the whole authentication of a request, from the
header to the security token through Symfony's `access_token` authenticator and the listener of the roles: about
27 µs on a laptop.

```sh
composer bench -- packages/api-keys-bundle/tests/Benchmark
```

## Testing an application that uses it

Keep tokens in memory and give the test environment a key of its own. The store in memory is emptied between two
requests, as in production under a worker: a test that reads what an earlier request wrote uses the Doctrine store
on SQLite, or creates its token through `ApiTokens` in the same request as the client's.

```yaml
# config/packages/test/phil_api_keys.yaml
phil_api_keys:
    store: memory
    hash_key: 'a-test-key-of-thirty-two-bytes!!'
```

```php
$tokens = self::getContainer()->get(ApiTokens::class);
$issued = $tokens->create('alice@example.com', 'test', ['projects:read']);

$client->request('GET', '/api/projects', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$issued->plainText]);
```

A `MockClock` as the bundle's `clock` moves a token past its expiry or its throttle interval.
