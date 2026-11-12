# OpenAPI verification

`ContractVerifier` checks an OpenAPI 3.0, 3.1 or 3.2 document against named levels of rules and returns a `Report`
of findings, each with its rule, its severity and its place in the document. The rules describe a REST API that
speaks JSON, without version numbers: the contract the rest of this package carries in its types. The package
verifies OpenAPI documents only: a GraphQL schema, an AsyncAPI document or a gRPC definition describes another kind of
API, which it does not cover.

```php
use Phil\ApiContract\OpenApi\{ContractVerifier, OpenApiDocument, RuleLevel, Severity};

$report = ContractVerifier::withBuiltInRules()->verify(OpenApiDocument::fromJson($json), RuleLevel::Recommended);

foreach ($report->findings as $finding) {
    echo $finding->severity->value, ' ', $finding->ruleId, ' at ', $finding->location, ': ', $finding->message, "\n";
}

exit($report->fails(Severity::Error) ? 1 : 0);
```

In a Symfony application, `phil/api-contract-bundle` runs the same verification from the console.

## Levels

Each level runs its own rules and those of the levels before it.

| Level | What a document that passes it is |
|---|---|
| `RuleLevel::Minimal` | An OpenAPI 3 document any tool reads the same way: a version, an `info`, paths, responses, path parameters that match their templates, unique operation ids, local references that resolve. |
| `RuleLevel::Recommended` | The contract of an unversioned JSON API: no version in a path, a success for every operation, JSON bodies, problem details for errors, a sunset for a deprecated operation, an id for every operation. The default. |
| `RuleLevel::Strict` | A contract a client reads without guessing: extensible enumerations, declared errors, a description and a security requirement for every operation. |

## Severities

| Severity | Meaning |
|---|---|
| `Severity::Error` | The document breaks the contract. |
| `Severity::Warning` | The document weakens the contract, or makes a deliberate exception to it. |

`Report::fails($threshold)` is `true` when one finding reaches the threshold: an error reaches both thresholds, a
warning only `Severity::Warning`. `Report::reaching($threshold)` lists those findings.

## The rules

| Id | Level | Severity | What it asks of the document |
|---|---|---|---|
| `openapi-version` | minimal | error | `openapi` is `3.0.x`, `3.1.x` or `3.2.x`. A Swagger 2.0 document fails. |
| `info` | minimal | error | An `info` object with a non-empty `title` and `version`. |
| `paths` | minimal | error | A `paths` object, each key starting with `/` and holding a path item object. |
| `operation-responses` | minimal | error | Every operation has a non-empty `responses` object. |
| `path-parameters` | minimal | error | Every `{name}` of a path template is a path parameter of each of its operations, declared on the path item or the operation, and marked `required: true`; every path parameter appears in its template. |
| `operation-id-unique` | minimal | error | No two operations share an `operationId`. |
| `local-references` | minimal | error | Every `$ref` that starts with `#` points at a value of the document. External references are not followed. |
| `no-version-in-path` | recommended | error | No path and no `servers[].url` has a segment such as `v1`, `V2` or `v3.0.1`. |
| `success-response` | recommended | error | Every operation declares a `2XX` or `3XX` response. |
| `json-content` | recommended | warning | Every request and response body of an operation is `application/json` or an `application/…+json` type. |
| `problem-details` | recommended | warning | Every `4XX`, `5XX` or `default` response with a body offers `application/problem+json`. |
| `deprecation-sunset` | recommended | warning | Every operation marked `deprecated: true` documents a `Sunset` header on one of its responses. |
| `operation-id` | recommended | warning | Every operation has a non-empty `operationId`. |
| `extensible-enum` | strict | warning | No schema has an `enum`: values that may grow are listed in `x-extensible-enum` and read with a default, as `TolerantEnum::withDefault()` does. The variables of a server URL are not schemas and are not reported. |
| `error-responses` | strict | warning | Every operation declares a `4XX` response or a `default` one. |
| `operation-description` | strict | warning | Every operation has a non-empty `summary` or `description`. |
| `security-requirement` | strict | warning | The document declares a top-level `security`, or else every operation declares its own; `security: []` says an operation is public on purpose. |

`json-content` is a warning so that an operation that serves a file, an image or a PDF, can pass a verification that
fails on errors only. `extensible-enum` reports a field of authority as well: that field keeps its `enum`, and the
document is verified at the recommended level, or the rule is left out with `ContractVerifier::without()`.

The response codes are compared as OpenAPI writes them: `200`, `2XX` or `2xx`, `default`. Responses, request bodies and
parameters given as local references are followed, up to sixteen references in a chain; a chain that loops or breaks
counts as absent, and `local-references` reports where it breaks.

## Findings and reports

| Type | Holds |
|---|---|
| `Finding` | `ruleId`, `severity`, `location`, `message`; `toArray()`. |
| `Report` | `level`, `findings` in the order the rules ran; `fails()`, `reaching()`, `toArray()`. |

The location is a JSON Pointer (RFC 6901) into the document: `/paths/~1orders~1{id}/get/responses`. The location and
the message quote the document, which anyone may have written, so each is kept to valid UTF-8 and 1024 characters, a
control character replaced by U+FFFD: a report printed in a terminal or a CI log shows the document's text without
obeying it.

## Reading a document

`OpenApiDocument::fromJson()` reads a JSON text; `OpenApiDocument::fromArray()` takes a document already decoded, from
YAML for instance. Both refuse with `UnreadableDocument` what cannot be verified at all:

| Bound | Value |
|---|---|
| Size of the JSON text | 8 MiB (`OpenApiDocument::MAX_BYTES`) |
| Depth | 128 levels (`OpenApiDocument::MAX_DEPTH`) |
| Values | 500 000 (`OpenApiDocument::MAX_VALUES`) |
| References followed in a chain | 16 |

A document that reads but breaks rules is not refused: its findings are in the report. Integers beyond PHP's range
are kept as strings. A JSON object key that PHP reads as an integer, a status code such as `200`, is handled as the
string it is.

The values of `example`, `examples` and `x-…` extensions are data, not description: the rules that walk the whole
document, `local-references` and `extensible-enum`, leave them out, unless they name the properties of a schema.

## The verifier's rules

`ContractVerifier::withBuiltInRules()` holds the seventeen rules above, in the order of the table, then any rule
passed to it. `new ContractVerifier($rules)` holds exactly the rules given. `without('operation-description')` returns a
verifier without those rules and refuses an id it does not hold, so that a typo does not keep a rule silently.
`rulesAt($level)` lists the rules a level runs.

A rule id is 1 to 64 lowercase letters, digits and hyphens, starting with a letter, and unique among the rules of a
verifier; the constructor refuses anything else with `InvalidContractValue`. An application's own rule implements
`Contract\OpenApiRule`: see [Extension points](extension-points.md#openapirule).

The verifier and the rules keep no state between documents: one instance verifies any number of them, from any
request or worker.
