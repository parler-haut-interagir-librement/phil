<!-- phil-dev source-sha256=b5284084b02cbceba9c0015c29068f3e4a0228fbad9fb977028fef05623512df -->
# Installation

| Prérequis | Contrainte | Pourquoi |
|---|---|---|
| PHP | `>=8.5` | Le minimum déclaré |
| `phil/api-contract` | `^0.1@dev` | Le contrat : problèmes et signaux |
| `phil/idempotency` | `^0.1@dev` | Les clés, les empreintes, la garde et ses stockages |
| `symfony/dependency-injection`, `symfony/config` | `^8.2` | La classe du bundle et sa configuration |
| `symfony/http-foundation`, `symfony/http-kernel` | `^8.2` | Les écouteurs et les attributs |
| `psr/clock` | `^1.0` | Le moment où une clé expire, où un signal est vu ; le service `clock` du conteneur, que FrameworkBundle enregistre dès que `symfony/clock` est installé |
| `doctrine/dbal`, `doctrine/orm`, DoctrineBundle | facultatif | Les clés d'idempotence dans une table |
| `symfony/lock`, un pool de cache | facultatif | Les clés d'idempotence plutôt dans un pool de cache |
| `symfony/http-client` | facultatif | Les obsolescences des API qu'appelle l'application |
| `psr/cache`, `psr/log` | facultatif | Le registre de ces obsolescences, l'avertissement |
| `ext-uri` | `*` | Le chemin d'une URL qu'appelle le client HTTP, lu par l'analyseur d'URL WHATWG de PHP |
| `symfony/console` | facultatif | `phil:api-contract:deprecations` et `phil:api-contract:verify` |
| `symfony/yaml` | facultatif | Un document OpenAPI écrit en YAML |
| `symfony/security-core` | facultatif | Les clés d'idempotence rattachées à l'utilisateur |

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

Avec DoctrineBundle, le bundle ajoute la table `phil_idempotency` de `phil/idempotency` au schéma de sa connexion :
créez-la avec une migration.

```bash
bin/console doctrine:migrations:diff
```
