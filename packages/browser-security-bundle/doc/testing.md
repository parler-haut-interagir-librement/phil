# Testing

| Area | What is covered |
|---|---|
| Directives and values | Every kind of value, every directive's grammar, the bounds, the unquoted keywords, the new lines |
| Policies | The order, `'none'`, flags, adding from `default-src`, replacing, removing, inheritance, the tokens of a request |
| Named policies | `extends` and its cycles, the hashes of inline code, Trusted Types, the options per policy |
| Overrides | The attributes, the route defaults and their refusals, the order of replace, add and remove, for the policy and for every other protection |
| Neighbouring headers | Every value and its refusal, the frame option of a path, a header kept or forced, HSTS over HTTPS only, the endpoint of the reports |
| HTTPS, cookies, redirects | The redirect and its exceptions, signatures and their rotation, targets resolved as a browser resolves them |
| Nonces and state | One nonce per kind and request, the shared nonce, hashes, the main request |
| Headers | Enforced and report-only, reporting directives and endpoint, `'strict-dynamic'`, element directives, overrides |
| The listener | Route and controller overrides, a response with its own policy, a response with a nonce made private, sub-requests, excluded paths, the report address |
| Reports | Both formats, the reports of the other policies, every field and its bound, privacy of the addresses, malformed bodies, the number of reports |
| The receiver and the endpoint | Each receipt, the throttle key, deduplication in a body and in the window, the store, the event |
| The adapters | The rate limiter and the stores the guard of phil/rate-limit watches, the limiter of phil/rate-limit-bundle, the PSR-6 deduplicator, the truncated address, the DBAL store and its race, the purge command |
| Twig | `csp_nonce()`, `csp_meta()`, `importmap()` with the nonce |
| User content | Every header, the inline types and the active ones, a response without type, the file name kept or given and made safe, the configuration and its refusals, the route default, the attribute and `apply()` in a kernel |
| The bundle | Every option and refusal of the configuration; a kernel with AssetMapper, Twig, SecurityBundle and SQLite serving pages and reports |

Infection holds the bundle at 100 % of mutants killed.

```sh
vendor/bin/phpunit packages/browser-security-bundle/tests
```

## Fuzzing

`tests/Fuzz/report.php` reads bodies nobody wrote under each media type: a body is read or refused with
`InvalidReport`, nothing else, and every report read keeps within its bounds — no query, fragment, credentials nor
control character in an address, a valid directive, a sample of 40 characters at most.

```sh
composer fuzz -- report
```

## Benchmark

`tests/Benchmark/HeaderBuilderBench.php` builds the headers of one response, with the default policy enforced and a
stricter one as report-only. On a recent laptop:

| Subject | Time |
|---|---|
| Without nonce | about 4 µs |
| With two nonces and a hash | about 9 µs |
| With an override of the route | about 18 µs |
| A new nonce | about 1 µs |

```sh
composer bench -- packages/browser-security-bundle/tests/Benchmark
```
