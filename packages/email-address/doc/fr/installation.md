<!-- phil-dev source-sha256=36994b6aa1856370f02cea7d89f0a3007816674e265c38a764af39585fab4c8a -->
# Installation

## Prérequis

PHP 8.5 ou plus récent, avec `ext-intl` (IDNA et normalisation Unicode), `ext-ctype` et `ext-filter` (littéraux IP) ;
`symfony/validator` 8.2 ou plus récent pour la contrainte, et `symfony/filesystem` 8.2 ou plus récent pour les
importateurs, tous deux installés avec la bibliothèque.

## Installer

```sh
composer require phil/email-address
```

## Comme service Symfony

La bibliothèque ne déclare aucun service. Une application qui veut un seul validateur partout le déclare :

```php
// config/services.php
use Phil\EmailAddress\DisposableDomains;
use Phil\EmailAddress\Dns\DnsGetRecordResolver;
use Phil\EmailAddress\EmailValidator;
use Phil\EmailAddress\TopLevelDomains;
use Phil\EmailAddress\Validator\DeliverableEmailAddressValidator;

$services->set(DnsGetRecordResolver::class);
$services->set(EmailValidator::class)
    ->arg('$disposable', service('app.disposable_domains'))
    ->arg('$resolver', service(DnsGetRecordResolver::class))
    ->arg('$topLevelDomains', service('app.top_level_domains'));
$services->set('app.disposable_domains', DisposableDomains::class)
    ->factory([DisposableDomains::class, 'bundled']);
$services->set('app.top_level_domains', TopLevelDomains::class)
    ->factory([TopLevelDomains::class, 'bundled']);
$services->set(DeliverableEmailAddressValidator::class)
    ->arg('$disposableDomains', service('app.disposable_domains'))
    ->arg('$topLevelDomains', service('app.top_level_domains'))
    ->arg('$resolver', service(DnsGetRecordResolver::class))
    ->tag('validator.constraint_validator');
```

La contrainte fonctionne aussi sans cette déclaration : le Validator bâtit alors son validateur lui-même, avec les
listes fournies et `dns_get_record()`. Voir [Validator de Symfony](symfony-validator.md).

Chaque classe est immuable et ne garde aucun état entre les appels : une seule instance sert toutes les requêtes d'un
*worker* de longue durée.
