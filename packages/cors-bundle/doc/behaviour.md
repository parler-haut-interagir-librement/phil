# How requests are answered

The bundle registers one listener, `phil_cors.listener`, on two kernel events. Both look only at main requests: a
sub-request, such as a fragment or an error controller, is left alone.

## Preflights

A preflight is an `OPTIONS` request with an `Origin` header and an `Access-Control-Request-Method` header. On
`kernel.request`, at priority 250, before the router (32) and the firewall (8), the listener finds the policy of the
path. Without one, the request goes on as any other. With one, the bundle answers at once, and no controller, route or
authenticator runs: a preflight carries no cookie and no credential, and needs no `OPTIONS` route.

The answer is always `204 No Content` with:

| Header | When |
|---|---|
| `Vary: Origin, Access-Control-Request-Method, Access-Control-Request-Headers` | Always; `Access-Control-Request-Private-Network` is added when the path allows the private network. |
| `Access-Control-Allow-Origin` | The origin, method and every requested header are allowed: the origin, or `*` when the path allows any origin. |
| `Access-Control-Allow-Credentials: true` | Allowed, and the path allows credentials. |
| `Access-Control-Allow-Methods` | Allowed: the methods of the path. |
| `Access-Control-Allow-Headers` | Allowed: the headers of the path, or the requested ones when the path allows any; not sent when there are none. |
| `Access-Control-Max-Age` | Allowed, and the path gives `max_age`. |
| `Access-Control-Allow-Private-Network: true` | Allowed, the path allows it, and the request sends `Access-Control-Request-Private-Network: true`; Chrome sends it no more, see [The private network](configuration.md#the-private-network). |

When the origin, the method or one of the requested headers is not allowed, the answer has only `Vary`: the browser
reads the missing `Access-Control-Allow-Origin` as a refusal and never sends the request itself. The reason is not
given to the page, as browsers give none either.

## Other responses

On `kernel.response`, for every response of a path that has a policy, error pages included, the listener:

1. adds `Origin` to `Vary`, keeping the values already there, whether or not the request has an `Origin` header, so
   that a cache never gives the answer for one origin to another or to a same-origin visit;
2. removes `Access-Control-Allow-Origin`, `Access-Control-Allow-Credentials` and `Access-Control-Expose-Headers`, so
   that a header a controller set never opens a path wider than its policy;
3. when the request has an allowed `Origin`, sets `Access-Control-Allow-Origin`, `Access-Control-Allow-Credentials`
   when the path allows credentials, and `Access-Control-Expose-Headers` when the path exposes headers.

A response to a path without a policy is left exactly as the application made it.

## What a request may send

The bundle bounds what it reads, so that a request never makes it work harder than a few comparisons:

| Value | Bound | Beyond it |
|---|---|---|
| `Origin` | 512 bytes | The origin is not allowed. |
| `Access-Control-Request-Headers` | 4096 bytes and 64 names | The preflight is refused. |

An exact origin is found in a hash table; patterns are tried in the order of the configuration, on a value of at most
512 bytes. The method is compared without case, as are the header names; the origin is compared as sent.

## Long-running workers

The listener, the headers service and the policies hold only the configuration, the same for every request: nothing
is kept from one request to the next, and the bundle needs no `kernel.reset`. It runs as is under FrankenPHP's worker
mode, RoadRunner or any other runtime that serves many requests with one kernel.
