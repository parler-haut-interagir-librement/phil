# Reference

Every public type of the package, with its role.

| Type | Kind | Role |
|---|---|---|
| `Phil\SafeHttp\SafeHttpClient` | `final readonly class`, reset | Reads an address within its limits, behind the guard: `get()`, `head()`, and `send()` of any method with headers and a body |
| `Phil\SafeHttp\Limits` | `final readonly class` | The silence, the whole duration, the bytes, the redirects, the `User-Agent` and the headers of a read |
| `Phil\SafeHttp\ReadRule` | `final readonly class` | The `Accept`, the media types read, the stop mark |
| `Phil\SafeHttp\SafeResponse` | `final readonly class` | The answer: address, redirects, status, headers, body |
| `Phil\SafeHttp\AddressGuard` | `final class` | The check of a first address and of a redirect |
| `Phil\SafeHttp\Exception\SafeHttpException` | interface | Every exception of the package |
| `Phil\SafeHttp\Exception\RefusedAddress` | `final class` | An address, a redirect, a redirect from HTTPS to HTTP or a host refused |
| `Phil\SafeHttp\Exception\TooManyRedirects` | `final class` | A chain of redirects too long |
| `Phil\SafeHttp\Exception\FetchFailed` | `final class` | No answer within the limits, or headers past them |
| `Phil\SafeHttp\Exception\RefusedRedirect` | `final class` | A redirect answered to a request that follows none |
| `Phil\SafeHttp\Exception\InvalidRequest` | `final class` | A method, a header or a body `send()` refuses |
| `Phil\SafeHttp\Exception\InvalidLimits` | `final class` | Limits or a header out of bounds |
