<!-- phil-dev source-sha256=73efbef9ebc93cc91e7fd8ccb67f0b7383a8cf33e0f524d4619f5702bf4e02c5 -->
# Installation

## Prérequis

PHP 8.5 ou plus récent, avec `ext-ctype`, `ext-iconv` (mots encodés), `ext-mbstring` et `ext-sodium` (décodage base64).

## Installer

```sh
composer require phil/bounce-parser
```

## Comme service Symfony

La bibliothèque ne déclare aucun service. Une application déclare l'analyseur avec ses règles, ses chemins de retour et
son authentificateur :

```php
// config/services.php
use Phil\BounceParser\Auth\AuthenticationResults;
use Phil\BounceParser\BounceParser;
use Phil\BounceParser\Verp;

$services->set(Verp::class)->args(['bounces', 'mail.example.net']);
$services->set(AuthenticationResults::class)->args([['mail.example.net'], ['fbl.example.com']]);
$services->set(BounceParser::class)
    ->arg('$verp', service(Verp::class))
    ->arg('$authenticator', service(AuthenticationResults::class));
```

Chaque classe est immuable et ne garde aucun état entre les appels : un seul analyseur sert chaque message d'un *worker*
de longue durée.
