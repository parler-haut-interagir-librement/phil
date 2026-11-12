# Reference

Every public type of the library, by topic. Namespaces are relative to `Phil\ApiContract`. All value objects
are immutable (`final readonly`), and constructors are private behind named constructors unless noted.

## Problem

| Type | Role | State |
|---|---|---|
| `ProblemDetails` | An RFC 9457 problem document: `create()` strict for the server, `fromArray()` tolerant for the client, `toArray()`, `hasType()`, `isBlank()`, constants `MEDIA_TYPE` and `BLANK_TYPE`. | Implemented and tested |

## Deprecation

| Type | Role | State |
|---|---|---|
| `DeprecationSignal` | `Deprecation`, `Sunset` and `Link: rel="deprecation"`: `of()` and `toHeaders()` for the server, `fromHeaders()` for the client, `isDeprecatedAt()`, `isSunsetAt()`, `noticeInDays()`. | Implemented and tested |
| `LinkHeader` | `firstWithRelation()`: the first target of a link relation across `Link` lines (RFC 8288 subset). | Implemented and tested |
| `ObservedDeprecations` | Port: the client's record of signals per endpoint, `record()` and `all()`. | Port only |

## Http

| Type | Role | State |
|---|---|---|
| `HttpDate` | `format()` to IMF-fixdate in UTC; `parse()` of the three RFC 9110 forms, `null` on anything else. | Implemented and tested |

## TolerantReading

| Type | Role | State |
|---|---|---|
| `TolerantEnum` | `withDefault()` for an ordinary field, `authoritative()` for a field of authority; strict, no coercion. | Implemented and tested |

## OpenApi

| Type | Role | State |
|---|---|---|
| `ContractVerifier` | Verifies an `OpenApiDocument` at a `RuleLevel`: `withBuiltInRules()`, `builtInRules()`, `without()`, `rulesAt()`, `verify()`; the public list `rules`. Rule ids are unique and well formed. | Implemented and tested |
| `OpenApiDocument` | A document read for verification: `fromJson()`, `fromArray()`, bounded by `MAX_BYTES`, `MAX_DEPTH` and `MAX_VALUES`; `operations()`, `at()` for a local reference, `resolve()` for a chain of them, `nodes()`, `pointer()`; constant `METHODS`. | Implemented and tested |
| `Operation` | One operation: `path`, `method`, `pointer`, `operation`, `pathItem`; `parameters()` and `responses()` with their references followed. Public constructor. | Implemented and tested |
| `RuleLevel` | Enum `Minimal`, `Recommended`, `Strict`; `includes()`. | Implemented and tested |
| `Severity` | Enum `Error`, `Warning`; `reaches()`. | Implemented and tested |
| `Finding` | Rule id, severity, JSON Pointer and message, the last two kept printable and bounded to `MAX_LENGTH`; `of()`, `toArray()`. Public constructor. | Implemented and tested |
| `Report` | The level and the findings; `fails()`, `reaching()`, `toArray()`. Public constructor. | Implemented and tested |

## OpenApi\Rule

Each rule is a `final readonly` class with a public constructor and a constant `ID`; see
[OpenAPI verification](openapi-verification.md#the-rules) for what each asks.

| Level | Rules |
|---|---|
| Minimal | `OpenApiVersion`, `Info`, `Paths`, `OperationResponses`, `PathParameters`, `OperationIdUnique`, `LocalReferences` |
| Recommended | `NoVersionInPath`, `SuccessResponse`, `JsonContent` (with the static `isJson()`), `ProblemDetails`, `DeprecationSunset`, `OperationId` |
| Strict | `ExtensibleEnum`, `ErrorResponses`, `OperationDescription`, `SecurityRequirement` |

## Contract

| Type | Role | State |
|---|---|---|
| `OpenApiRule` | Interface of a rule of verification: `id()`, `level()`, `severity()`, `description()`, `check()`. | Interface |

## Exception

| Type | Role | State |
|---|---|---|
| `ApiContractException` | Marker interface extending `Throwable`: catch everything the library throws with one `catch`. | Implemented |
| `InvalidContractValue` | `InvalidArgumentException`: a value object refused its own construction; `because(string)`. | Implemented and tested |
| `UnknownAuthorityValue` | `UnexpectedValueException`: an unknown value in a field of authority; `for(class-string, mixed)`, message bounded to 64 bytes and escaped. | Implemented and tested |
| `UnreadableDocument` | `InvalidArgumentException`: an OpenAPI document that cannot be verified at all; `because(string)`. | Implemented and tested |

## Not in the library

- A Symfony service, configuration, event listener, HTTP client decorator or console command, an adapter for
  `ObservedDeprecations`, and the reading of a YAML file: `phil/api-contract-bundle` brings them.
- The comparison of two versions of a document, and the check of real traffic against one: the verification reads one
  document.
- Idempotency keys, fingerprints and stores: `phil/idempotency`.
- The unknown-field half of tolerant reading, which belongs to the deserializer.
