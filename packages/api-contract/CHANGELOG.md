# Changelog

All notable changes to `phil/api-contract` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- Problem details: `Problem\ProblemDetails`.
- Deprecation signals: `Deprecation\DeprecationSignal`, `Deprecation\LinkHeader`, `Http\HttpDate`; the port
  `Deprecation\ObservedDeprecations`.
- Tolerant reading: `TolerantReading\TolerantEnum`.
- OpenAPI verification: `OpenApi\ContractVerifier`, `OpenApiDocument`, `Operation`, `RuleLevel` (minimal,
  recommended, strict), `Severity`, `Finding` and `Report`; the interface `Contract\OpenApiRule`; seventeen rules in
  `OpenApi\Rule`, for an OpenAPI 3.0, 3.1 or 3.2 document of a REST API that speaks JSON without version numbers.
  Reading is bounded to 8 MiB, 128 levels and 500 000 values; findings quote the document kept printable and bounded.
- Exceptions: `ApiContractException`, `InvalidContractValue`, `UnknownAuthorityValue`, `UnreadableDocument`.
- The fuzz target `openapi-verifier`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
