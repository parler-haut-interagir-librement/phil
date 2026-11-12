# Verification

`phil:api-contract:verify` checks the application's OpenAPI document against a level of rules of `phil/api-contract`,
prints each finding, and fails when one reaches the threshold. Run in CI, it keeps the API as its document describes
it, and the document as the contract asks: REST, JSON, no version in a path, problem details for errors.

```bash
bin/console phil:api-contract:verify openapi.yaml
# warning json-content at /paths/~1orders/get/responses/200/content/text~1csv: The media type "text/csv" is not JSON.
# 0 error(s), 1 warning(s) at the recommended level.
```

The levels, the seventeen rules and their severities are described with the library, in its documentation of the
OpenAPI verification.

## Arguments and options

| Argument or option | Values | Default |
|---|---|---|
| `document` | A JSON file, or a YAML file whose name ends in `.yaml` or `.yml` | `verification.document` |
| `--level` | `minimal`, `recommended`, `strict` | `verification.level`, `recommended` |
| `--fail-on` | `error`, `warning` | `verification.fail_on`, `error` |
| `--format` | `text`, `json` | `text` |

`--format=json` writes the report as `{"level": …, "findings": [{"rule", "severity", "location", "message"}]}`, for a
CI step that annotates the document.

## Exit codes

| Code | When |
|---|---|
| `0` | No finding reaches the threshold. |
| `1` | A finding reaches the threshold: an error, or with `--fail-on=warning` an error or a warning. |
| `2` | No document is named or configured, an option has a value it does not take, or the document cannot be read: missing, larger than 8 MiB, not JSON, not YAML, deeper than 128 levels. |

A YAML document needs `symfony/yaml`; without it, the command answers `2` and says so. The document's text is printed
escaped: a path or a reference that holds console tags or control characters is shown, not obeyed.

## Configuration

```yaml
# config/packages/phil_api_contract.yaml
phil_api_contract:
    verification:
        document: '%kernel.project_dir%/openapi.yaml'
        level: strict
        fail_on: warning
        skip: [operation-description]
```

`skip` names rules the verifier leaves out; a name that is no rule's id fails when the verifier is built, so that a
typo does not keep a rule silently. See [Configuration](configuration.md).

## The application's own rules

A service that implements `Phil\ApiContract\Contract\OpenApiRule` is tagged `phil_api_contract.openapi_rule` by
autoconfiguration and runs after the built-in rules, at the level it declares. Without autoconfiguration, tag it:

```yaml
# config/services.yaml
services:
    App\Api\PluralCollections:
        tags: [phil_api_contract.openapi_rule]
```

The library's documentation of its extension points gives the contract of a rule and an example.

## In a CI pipeline

```yaml
# a step of the pipeline
- run: bin/console phil:api-contract:verify --level=strict --fail-on=warning
```

The verifier is the public service `Phil\ApiContract\OpenApi\ContractVerifier`: a test of the application can verify a
document it generates in the same way.
