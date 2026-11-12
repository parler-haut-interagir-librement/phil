# Testing

```sh
vendor/bin/phpunit packages/safe-http/tests
```

| Area | What is covered |
|---|---|
| `Limits`, `ReadRule` | The defaults, each bound and the value past it, headers on two lines or with a control character |
| `AddressGuard` | Addresses normalised; IP addresses in every spelling, credentials, port 0, other schemes, single labels and lengths refused; redirects absolute, relative, without a scheme and with `..`; redirects to other schemes refused |
| `SafeHttpClient`, `SafeResponse` | The limits in each request, the body cut at its bytes or after its mark — split between two chunks too —, bodies of other types not read, error statuses as answers, `HEAD`, each kind of redirect followed and checked, the count of redirects, hosts that resolve to the loopback, a private network or the metadata endpoint refused at the start and at a redirect, transport errors, the whole duration across redirects, `reset()` |

The tests use Symfony's `MockHttpClient` and fixed addresses: no test resolves a name or opens a connection.

## Fuzzing

The target `safe-http-addresses` gives the guard addresses and redirects nobody wrote, from the seeds of
`tests/Fuzz/corpus/safe-http-addresses`: the first line as an address, the second as a redirect from a known address.
Each gives an address that reads back as itself, or `RefusedAddress`, never another exception.

```sh
composer fuzz -- safe-http-addresses
```
