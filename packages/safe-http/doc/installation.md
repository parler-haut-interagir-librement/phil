# Installation

```sh
composer require phil/safe-http
```

| Package | Version | Used for |
|---|---|---|
| PHP | 8.5 or later, with the mbstring and URI extensions | The messages of the exceptions, the resolution of redirects |
| `phil/url` | ^0.1 | The addresses |
| `symfony/http-client`, `symfony/http-client-contracts` | ^8.2, ^3.6 | The transport and `NoPrivateNetworkHttpClient` |
| `symfony/http-foundation` | ^8.2 | `IpUtils`, the private ranges |
| `symfony/service-contracts` | ^3.6 | `ResetInterface` |

In a Symfony application, register one client per use, each with its limits, and tag it `kernel.reset` with the method
`reset`:

```php
$services->set('app.feed_client', SafeHttpClient::class)
    ->args([service('http_client')->nullOnInvalid(), inline_service(Limits::class)->args([5.0, 20.0, 4_194_304, 3, 'ExampleBot/1.0 (+https://example.com/bot)'])])
    ->tag('kernel.reset', ['method' => 'reset']);
```
