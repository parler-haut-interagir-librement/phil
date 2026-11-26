# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: each refused value of a policy
with its message, the origins exact, by pattern and at the bounds, the answer to a preflight allowed or refused, the
headers of a response, the listener on main requests and sub-requests, and the configuration, from the environment
too. The functional suite, under `tests/Functional`, boots a small application — FrameworkBundle and two rules — and
sends it preflights and requests: a preflight answered without a route, a controller's wildcard removed for another
origin, an error page with the headers, and a path outside the rules left alone. In the monorepo, Infection holds the
package at 100 % of covered mutants.

## Testing an application's rules

A functional test of an application checks its own rules the same way, with the browser's headers:

```php
$client->request('OPTIONS', '/api/books', server: [
    'HTTP_ORIGIN' => 'https://app.example.com',
    'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PUT',
    'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
]);

self::assertResponseStatusCodeSame(204);
self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'https://app.example.com');
```
