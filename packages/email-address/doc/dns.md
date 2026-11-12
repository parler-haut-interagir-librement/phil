# DNS

`Contract\MailDomainResolver::resolve(string $asciiDomain): MailDomain` says whether a domain receives mail. The
validator asks it with the ASCII form of the domain, without a final dot.

| `MailDomain` | The DNS said | `acceptsMail()` |
|---|---|---|
| `Mx` | One or more MX records | `true` |
| `AddressOnly` | No MX, but an A or AAAA record: the host receives its own mail (RFC 5321, section 5.1) | `true` |
| `NullMx` | A single MX record whose exchange is the root (RFC 7505): the domain receives no mail | `false` |
| `NoRecord` | No MX, A nor AAAA record | `false` |
| `Unknown` | The lookup failed | `null` |

## `Dns\DnsGetRecordResolver`

Asks the system's resolver through `dns_get_record()`: MX first, then A, then AAAA, stopping at the first answer. The
host is written with a final dot, so that no search domain of the machine is appended. A failed lookup is `Unknown`.

`dns_get_record()` blocks for as long as the system's resolver waits; `timeout` and `attempts` in `resolv.conf` bound
it. A form that cannot wait checks the DNS later — in a Messenger handler that receives the identifier of the
subscription, never the address — or wraps the resolver with its own deadline.

A closure given to the constructor replaces `dns_get_record()`: it receives the host and the record type and returns
the records, or `false` when the lookup failed.

## `Dns\StaticResolver`

Answers from a map of ASCII domains, and with one answer for the others, `MailDomain::Unknown` by default: for tests,
for domains the application knows, or to switch the check off in an environment without DNS.

## Caching

The library caches nothing: a resolver keeps no state between calls, so that a long-running worker serves each request
alike. An application that checks many addresses of the same domains wraps the resolver in its own adapter backed by
its cache, keyed by the ASCII domain, for a few minutes — a domain that loses its MX should not stay accepted for days.
