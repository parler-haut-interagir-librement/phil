<!-- phil-dev source-sha256=296f0597fb6c264e9eed9201a3dae23d457af6f4487f5851abcfc3752c687b4c -->
# Installation

Cette page mène une application nue jusqu'à un bundle fonctionnel : les prérequis, Composer, la classe du bundle, les
tables de la base de données, les routes d'administration et le pare-feu devant elles. La liste complète des options se
trouve dans [configuration.md](configuration.md).

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Déclaré `>=8.5` ; le code utilise les constantes de classe typées, les classes `readonly` et `#[Override]`. |
| `ext-mbstring` | toute | Les raisons, les acteurs, les descriptions et les responsables sont vérifiés en UTF-8 et mesurés en caractères. |
| `ext-hash` | fournie avec PHP | Les compartiments stables des déploiements progressifs et des expériences utilisent l'algorithme `murmur3a` de `hash()`. Elle est toujours compilée depuis PHP 7.4 ; elle n'est donc pas déclarée. |
| `doctrine/dbal` | `^4.5` | Le magasin en base de données et son schéma. |
| `doctrine/orm` | `^3.7` | L'écouteur de schéma se branche sur l'événement `postGenerateSchema` de l'ORM. |
| `phil/circuit-breaker`, `phil/circuit-breaker-bundle` | `^0.1@dev` | Le port `CircuitOverride` qu'implémentent les indicateurs d'ouverture forcée, et les circuits dont les clés déclarent ces indicateurs. |
| `phil/ddd-bundle` | `^0.1@dev` | Les exceptions `InvalidValue` et `DomainError` du domaine. |
| `phil/enums` | `^0.3@dev` | `AsValuableEnumTrait` sur `FlagKind` et `TargetingAttribute`, dont `values()` écrit les messages d'erreur et les genres du formulaire d'administration. |
| `psr/clock` | `^1.0` | Chaque horodatage (lignes d'audit, expiration) vient d'une horloge PSR-20. |
| `symfony/config`, `console`, `dependency-injection`, `http-foundation`, `http-kernel`, `routing` | `^8.2` | L'arbre de configuration, la commande de liste, le câblage, le contrôleur d'administration, l'attribut de contrôleur, la condition de route. |
| `twig/twig` | `^3.21` | Les gabarits d'administration et les fonctions `#[AsTwigFunction]`. |

**Doctrine DBAL 4.5.** `FlagTables` construit ses tables avec `Schema::edit()` et les éditeurs de table, de colonne et
de clé primaire, et `FlagSchemaListener` rend le schéma avec `GenerateSchemaEventArgs::setSchema()`, qui lance une
exception à moins que `Schema::edit()` existe (DBAL 4.5 et plus récent). C'est pourquoi le bundle exige `^4.5`.

Le bundle enregistre ses services avec des étiquettes sur lesquelles agissent d'autres bundles ; en pratique,
l'application a donc aussi :

| Bundle | Nécessaire pour |
|---|---|
| `symfony/framework-bundle` | Le service `clock` par défaut (avec `symfony/clock`), le service `router` qu'utilise l'administration, les étiquettes `kernel.reset`, `console.command`, `kernel.event_listener` et `routing.condition_service`. |
| `symfony/twig-bundle` | Le service `twig` de l'administration, et les étiquettes `twig.attribute_extension` / `twig.runtime` des fonctions Twig. |
| `doctrine/doctrine-bundle` | Seulement avec le magasin en base de données : le service `doctrine.dbal.<name>_connection` et l'étiquette `doctrine.event_listener`. |
| `symfony/expression-language` | Seulement pour les conditions de route (`condition: "service('feature_flags')…"`). |

Bases de données : le magasin en base de données est testé sur SQLite, MySQL 8 et PostgreSQL.

## Installer avec Composer

```bash
composer require phil/feature-flags-bundle
```

Le bundle exige `phil/ddd-bundle`, `phil/circuit-breaker` et `phil/circuit-breaker-bundle` à une stabilité de
développement (`^0.1@dev`). Tant qu'ils ne sont pas publiés, l'application déclare pour eux un dépôt `path` ou `vcs` et
accepte `@dev` pour ces paquets.

## Enregistrer le bundle

Avec Symfony Flex, le bundle est ajouté pour vous à `config/bundles.php` ; sinon, ajoutez-le à la main, après les
bundles dont il dépend :

```php
// config/bundles.php
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],   // database store only
    Phil\CircuitBreakerBundle\PhilCircuitBreakerBundle::class => ['all' => true],   // circuits forced open by a flag
    Phil\FeatureFlagsBundle\PhilFeatureFlagsBundle::class => ['all' => true],
];
```

Sans `PhilCircuitBreakerBundle`, aucun circuit n'est déclaré, ni aucun indicateur d'ouverture forcée ; les indicateurs
fonctionnent de la même façon.

## La plus petite configuration

La clé de configuration est `phil_feature_flags`. Sans aucun adaptateur, avec des indicateurs déclarés en YAML et un
contexte vide :

```yaml
# config/packages/phil_feature_flags.yaml
phil_feature_flags:
    context_resolver: phil_feature_flags.context_resolver.anonymous
    flags:
        new-checkout: true
```

Sans `context_resolver`, le bundle attend un service nommé d'après l'interface
`Phil\FeatureFlagsBundle\Application\Flag\EvaluationContextResolver` : implémentez-la dans `src/` (avec l'autowiring,
une implémentation unique reçoit l'alias de l'interface) ; voyez [extension-points.md](extension-points.md).

## Le magasin en base de données et sa migration

Activez le magasin pour conserver dans la base de données les états actifs et les indicateurs créés dans
l'administration :

```yaml
phil_feature_flags:
    doctrine:
        connection: default     # a DBAL connection name of DoctrineBundle
```

Le magasin possède deux tables, `phil_feature_flags_definition` et `phil_feature_flags_state`
([database-store.md](database-store.md) décrit leurs colonnes). Le bundle ne fournit pas de migration :
`FlagSchemaListener` ajoute les deux tables au schéma que l'ORM génère pour cette connexion, et les outils habituels les
voient donc :

```bash
bin/console doctrine:migrations:diff        # writes the migration that creates the two tables
bin/console doctrine:migrations:migrate
bin/console doctrine:schema:validate        # does not ask to drop them
```

L'écouteur ne s'exécute que lorsque l'outil de schéma de l'ORM s'exécute pour un gestionnaire d'entités sur cette même
connexion. Une application sans gestionnaire d'entités sur cette connexion crée les tables à partir de la même
définition :

```php
use Doctrine\DBAL\Schema\Schema;
use Phil\FeatureFlagsBundle\Infrastructure\Doctrine\FlagTables;

foreach (FlagTables::configure(new Schema())->toSql($connection->getDatabasePlatform()) as $sql) {
    $connection->executeStatement($sql);
}
```

## La bascule

Changer un indicateur à l'exécution exige la bascule auditée, qui exige un magasin d'états (le magasin en base de
données en fournit un) et deux adaptateurs de l'application, un autorisateur et un auditeur :

```yaml
phil_feature_flags:
    doctrine: true
    toggle:
        authorizer: App\Flags\PermissionAuthorizer
        auditor: App\Flags\AuditTrailAuditor
```

Voyez [toggles-and-edits.md](toggles-and-edits.md) et [extension-points.md](extension-points.md).

## Les routes d'administration

Activez les écrans (ils exigent la bascule) et nommez le service qui dit qui est l'opérateur :

```yaml
phil_feature_flags:
    admin:
        actor_provider: App\Flags\OperatorProvider
```

Importez ensuite les routes du bundle sous un préfixe de votre choix :

```yaml
# config/routes/phil_feature_flags.yaml
phil_feature_flags_admin:
    resource: '@PhilFeatureFlagsBundle/config/routes/admin.php'
    prefix: /admin/flags
```

Les cinq routes qu'il déclare sont listées dans [admin.md](admin.md).

## Le pare-feu devant l'administration

Le bundle n'authentifie personne. Placez le préfixe derrière le pare-feu de l'application et exigez un rôle :

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/admin/flags, roles: ROLE_FLAG_OPERATOR }
```

À l'intérieur, l'`ActorProvider` nomme l'opérateur (un écran répond 403 lorsqu'il retourne `null`), l'autorisateur
décide de chaque changement, et chaque envoi de formulaire est vérifié comme provenant de la même origine. Les détails
sont dans [admin.md](admin.md).

## Vérifier l'installation

```bash
bin/console debug:config phil_feature_flags
bin/console phil:feature-flags:list
```

La liste montre chaque indicateur que déclarent la configuration et la base de données, avec son état et sa source
([console.md](console.md)).
