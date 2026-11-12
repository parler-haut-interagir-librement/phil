# Extension points

The library is plain PHP and declares no service. What an application chooses goes through one port, arguments and
data.

## `Contract\MailDomainResolver`

| Method | Contract |
|---|---|
| `resolve(string $asciiDomain): MailDomain` | Takes a domain in ASCII without a final dot; returns what the DNS says, `MailDomain::Unknown` when it cannot tell; keeps no state between calls unless it is a cache the application owns |

`Dns\DnsGetRecordResolver` asks the system's resolver, `Dns\StaticResolver` answers from a map. An adapter of the
application may cache, use another resolver, or answer from a list — see [DNS](dns.md).

## Arguments

| Point | How |
|---|---|
| The forms of an address accepted | `SyntaxPolicy` and its `with` methods — see [Syntax](syntax.md) |
| The domains refused, or the only ones accepted | `DomainRules::of()`, `withDenied()`, `withAllowedOnly()` — see [Domains](domains.md) |
| The top-level domains | `TopLevelDomains::bundled()`, `of()` — see [Domains](domains.md) |
| The disposable domains | `DisposableDomains::bundled()`, `of()`, `merge()` |
| The domains suggested and the slips replaced | The `$domains` and `$slips` of `TypoSuggester` — see [Suggestions](suggestions.md) |
| The steps run | The arguments of `EmailValidator`: a step whose argument is `null` is skipped |
| The constraint's options, messages and codes | The arguments of `Validator\DeliverableEmailAddress` — see [Symfony Validator](symfony-validator.md) |
| The lists and resolver of the constraint | The arguments of `Validator\DeliverableEmailAddressValidator`, declared as a service |
| Catching the library's errors | Every exception implements `Exception\EmailAddressException` |

## Data

The bundled list of disposable domains is imported by `Data\DisposableDomainsImporter` through
`bin/import-disposable-domains.php`, and the list of top-level domains by `Data\TopLevelDomainsImporter` through
`bin/import-top-level-domains.php`; neither is edited by hand.
