# phil/api-contract

**Problem details, deprecation signals, tolerant reading and OpenAPI verification for an HTTP API without version
numbers.**

phil/api-contract holds both sides of an unversioned HTTP API contract: the server that keeps it and the client
that reads it. It writes RFC 9457 problem details strictly and reads them tolerantly. It carries the `Deprecation`
(RFC 9745), `Sunset` (RFC 8594) and `Link: rel="deprecation"` signals. It reads enumerations tolerantly, and
refuses an unknown value in a field of authority. It verifies an OpenAPI document against three named levels of rules,
from a document any tool reads to a contract a client reads without guessing, and reports each finding with its rule,
its severity and its place in the document. It does not listen to requests, decorate an HTTP client or store
anything. Idempotency keys live in `phil/idempotency`.

The contract is REST and JSON, with no `/v1` in the path; GraphQL is outside it. Every change is additive, and a change
that cannot be additive becomes a new resource next to the old one, which announces its own retirement in every
response. The package is for developers who publish or consume such an API and want those rules in types and in CI
rather than in conventions.

It is a plain PHP library, usable in any PHP code, without dependencies. The record of observed deprecations is a
port (`ObservedDeprecations`) that the caller implements, and a rule of verification an interface
(`Contract\OpenApiRule`) that an application implements for its own rules. In a Symfony application, reach for
`phil/api-contract-bundle`, which wires listeners, `#[Idempotent]` controllers, an observing HTTP client and a
verification command on top of it.

## Status

| Area | Types | State |
|---|---|---|
| Problem details | `Problem\ProblemDetails` | Implemented and tested |
| Deprecation signals | `Deprecation\DeprecationSignal`, `Deprecation\LinkHeader`, `Http\HttpDate` | Implemented and tested |
| Record of observed deprecations | `Deprecation\ObservedDeprecations` | Port |
| Tolerant reading | `TolerantReading\TolerantEnum`, `Exception\UnknownAuthorityValue` | Implemented and tested |
| OpenAPI verification | `OpenApi\ContractVerifier`, `OpenApiDocument`, `RuleLevel`, `Severity`, `Finding`, `Report`, the 17 rules of `OpenApi\Rule` | Implemented and tested |
| Rule of verification | `Contract\OpenApiRule` | Interface |
| Exceptions | `Exception\ApiContractException`, `InvalidContractValue`, `UnreadableDocument` | Implemented |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements. |
| [Problem details](doc/problem-details.md) | RFC 9457, `create()` for the server and `fromArray()` for the client, every check and its reason. |
| [Deprecation and Sunset](doc/deprecation-and-sunset.md) | Writing and reading the three headers, HTTP-dates, `Link` parsing, the record of observed deprecations. |
| [Tolerant reading](doc/tolerant-reading.md) | Unknown enumeration values: a default, or a refusal in a field of authority. |
| [OpenAPI verification](doc/openapi-verification.md) | The three levels, the seventeen rules, findings and reports, the bounds of reading. |
| [Extension points](doc/extension-points.md) | `ObservedDeprecations`, with an example adapter; `OpenApiRule`, with an example rule. |
| [Testing](doc/testing.md) | What the suite pins, and how to test your adapters. |
| [Reference](doc/reference.md) | Every public type. |

## Quick start

```php
use Phil\ApiContract\Deprecation\DeprecationSignal;
use Phil\ApiContract\Problem\ProblemDetails;

$problem = ProblemDetails::create(422, 'https://example.com/problems/out-of-stock', 'Out of stock');
// json_encode($problem->toArray()), Content-Type: application/problem+json

$signal = DeprecationSignal::fromHeaders($headers['deprecation'] ?? null, $headers['sunset'] ?? null, $headers['link'] ?? []);
```

```php
use Phil\ApiContract\OpenApi\{ContractVerifier, OpenApiDocument, RuleLevel};

$report = ContractVerifier::withBuiltInRules()->verify(OpenApiDocument::fromJson($json), RuleLevel::Strict);
$report->fails(); // true when one finding is an error
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
