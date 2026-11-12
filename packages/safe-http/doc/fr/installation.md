<!-- phil-dev source-sha256=74cd861e972eb744f5679b51cf324d85aca9e45441de4e4990b2ab4c20157218 -->
# Installation

```sh
composer require phil/safe-http
```

| Paquet | Version | Sert à |
|---|---|---|
| PHP | 8.5 ou plus récent, avec les extensions mbstring et URI | Les messages des exceptions, la résolution des redirections |
| `phil/url` | ^0.1 | Les adresses |
| `symfony/http-client`, `symfony/http-client-contracts` | ^8.2, ^3.6 | Le transport et `NoPrivateNetworkHttpClient` |
| `symfony/http-foundation` | ^8.2 | `IpUtils`, les plages privées |
| `symfony/service-contracts` | ^3.6 | `ResetInterface` |

Dans une application Symfony, enregistrez un client par usage, chacun avec ses limites, et étiquetez-le `kernel.reset`
avec la méthode `reset` :

```php
$services->set('app.feed_client', SafeHttpClient::class)
    ->args([service('http_client')->nullOnInvalid(), inline_service(Limits::class)->args([5.0, 20.0, 4_194_304, 3, 'ExampleBot/1.0 (+https://example.com/bot)'])])
    ->tag('kernel.reset', ['method' => 'reset']);
```
