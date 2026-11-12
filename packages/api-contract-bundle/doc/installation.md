# Installation

| Requirement | Constraint | Why |
|---|---|---|
| PHP | `>=8.5` | The declared minimum |
| `phil/api-contract` | `^0.1@dev` | The contract: problems and signals |
| `phil/idempotency` | `^0.1@dev` | Keys, fingerprints, the guard and its stores |
| `symfony/dependency-injection`, `symfony/config` | `^8.2` | The bundle class and its configuration |
| `symfony/http-foundation`, `symfony/http-kernel` | `^8.2` | The listeners and the attributes |
| `psr/clock` | `^1.0` | When a key expires, when a signal is seen; the container's `clock` service, which FrameworkBundle registers once `symfony/clock` is installed |
| `doctrine/dbal`, `doctrine/orm`, DoctrineBundle | optional | The idempotency keys in a table |
| `symfony/lock`, a cache pool | optional | The idempotency keys in a cache pool instead |
| `symfony/http-client` | optional | The deprecations of the APIs the application calls |
| `psr/cache`, `psr/log` | optional | The record of those deprecations, the warning |
| `ext-uri` | `*` | The path of a URL the HTTP client calls, read by PHP's WHATWG URL parser |
| `symfony/console` | optional | `phil:api-contract:deprecations` and `phil:api-contract:verify` |
| `symfony/yaml` | optional | An OpenAPI document written in YAML |
| `symfony/security-core` | optional | Idempotency keys scoped by user |

```bash
composer require phil/api-contract-bundle:^0.1@dev
```

```php
// config/bundles.php
return [
    // ...
    Phil\ApiContractBundle\PhilApiContractBundle::class => ['all' => true],
];
```

With DoctrineBundle, the bundle adds the table `phil_idempotency` of `phil/idempotency` to the schema of its connection:
create it with a migration.

```bash
bin/console doctrine:migrations:diff
```
