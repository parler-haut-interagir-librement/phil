<!-- phil-dev source-sha256=03d1ff1b2bdda03f0b4bd96d7183a9bdfe852444f88b3b5aa9c33800dcf9ef56 -->
# Tests

## Les suites du bundle

| Suite | Répertoire | Démarre un noyau | Ce qu'elle couvre |
|---|---|---|---|
| Unitaire | `tests/Unit` | non | Le domaine (noms, compartiments aux valeurs fixées, règles, expériences, métadonnées, demandes), les services applicatifs (évaluateur, catalogue, bascule, éditeur, le forçage qui ouvre un circuit), les lecteurs de configuration, la passe de compilation des indicateurs d'ouverture forcée, et le câblage du bundle sur un `ContainerBuilder` (`PhilFeatureFlagsBundleTest`), seul et avec `phil/circuit-breaker-bundle`. |
| Fonctionnelle | `tests/Functional` | oui, `tests/Functional/App/TestKernel` | L'administration, le magasin en base de données sur une vraie connexion (écritures conditionnelles, lecture par requête, rangées illisibles signalées et évaluées comme `PARSE_ERROR`, génération du schéma), l'attribut de contrôleur, la condition de route, les fonctions Twig, les expositions et la commande de liste. |

Les tests unitaires utilisent des jeux de données d'essai en mémoire tirés de `tests/Unit/Fixture` (`InMemoryFlagStore`,
`InMemoryDefinitions`, `InMemoryCircuitStore`, `ListAuthorizer`, `RecordingAuditor`, `RecordingExposures`,
`FrozenClock`, `CountingProvider`, `CountingResolver`, `SwitchableResolver`, `CallJournal`), qui sont aussi de bons
modèles pour les doublures de test d'une application.

### Le noyau de test

`TestKernel` est la plus petite application dont le bundle a besoin : FrameworkBundle, DoctrineBundle, TwigBundle et le
bundle, avec le magasin en base de données, l'administration sous `/admin/flags`, quelques contrôleurs derrière des
indicateurs, trois indicateurs configurés (`new-checkout`, `checkout-layout`, `forgotten` expiré en 2020), le locataire
tiré de l'en-tête `X-Tenant` et l'opérateur tiré de `X-Operator` ; seul l'opérateur `operator` peut changer un
indicateur. `FunctionalTestCase` supprime et recrée les deux tables d'indicateurs avant chaque test et fait passer
chaque requête par le noyau comme le ferait un worker persistant : les services sont donc réinitialisés entre deux
requêtes.

### La base de données

Par défaut, la suite fonctionnelle s'exécute sur un fichier SQLite dans le répertoire de cache du noyau (sous le
répertoire temporaire du système). Définissez `PHIL_FEATURE_FLAGS_TEST_DATABASE_URL` pour l'exécuter sur un autre
serveur :

```bash
PHIL_FEATURE_FLAGS_TEST_DATABASE_URL='mysql://root:secret@127.0.0.1:3306/flags' vendor/bin/phpunit packages/feature-flags-bundle/tests/Functional
PHIL_FEATURE_FLAGS_TEST_DATABASE_URL='postgresql://app:secret@127.0.0.1:5432/app_test?serverVersion=17' vendor/bin/phpunit packages/feature-flags-bundle/tests/Functional
```

Le conteneur compilé est mis en cache par URL de base de données. Les tests suppriment et créent
`phil_feature_flags_definition` et `phil_feature_flags_state` dans cette base : faites-la pointer vers une base jetable.

## Exécuter à partir de la racine du monodépôt

Le bundle est incubé dans un monodépôt dont le `vendor/` et le `phpunit.dist.xml` racines exécutent ses tests :

```bash
vendor/bin/phpunit packages/feature-flags-bundle/tests            # both suites
vendor/bin/phpunit packages/feature-flags-bundle/tests/Unit
composer test:platforms                                           # the functional suite on MySQL 8 and PostgreSQL
```

La configuration racine place `tests/Unit` dans sa suite `Unit` et `tests/Functional` dans sa suite `Integration`.
`composer test:platforms` démarre un conteneur MySQL 8 jetable (`mysql:8.4` par défaut, que `MYSQL_IMAGE` remplace, sur
un port local aléatoire, supprimé à la sortie) et utilise le serveur PostgreSQL de la pile locale (base `app_test`, avec
`serverVersion=17` ajouté lorsque l'URL n'en a pas), puis exécute `tests/Functional` une fois sur chacun en définissant
`PHIL_FEATURE_FLAGS_TEST_DATABASE_URL`. Il exige Docker et le service PostgreSQL en marche ; c'est cette exécution qui
prouve que les booléens et la colonne JSON se comportent de la même façon sur les deux serveurs.

`composer packages` installe chaque bundle seul dans un répertoire neuf, exécute `composer-dependency-analyser` avec le
`composer-dependency-analyser.php` du bundle (chaque paquet déclaré doit être importé, chaque paquet importé doit être
déclaré), puis les tests du bundle avec `vendor/bin/phpunit --no-configuration tests`.

## Exécuter le bundle installé seul

```bash
cd packages/feature-flags-bundle      # or a checkout of the bundle
composer install                      # phil/ddd-bundle must be reachable through a path or vcs repository
vendor/bin/phpunit --no-configuration tests
```

Les dépendances de développement (`doctrine/doctrine-bundle`, `phpunit/phpunit`, `psr/log`, `symfony/clock`,
`symfony/expression-language`, `symfony/framework-bundle`, `symfony/twig-bundle`) sont ce dont le noyau de test a
besoin.

## Tests de mutation

Infection exige du domaine (`src/Domain`) un score de mutation de 100 % : il exécute la suite `Unit` sur chaque
répertoire `Domain` des bundles du monodépôt (`Application` et `Infrastructure` sont exclus), avec `minMsi` et
`minCoveredMsi` à 100. À partir de la racine :

```bash
composer infection
```

Un mutant survivant dans `Domain` signale un test manquant ou du code inaccessible. Ce sont les valeurs de compartiment
fixées dans `BucketTest` et `ExperimentTest` qui tuent tout mutant de l'entrée de l'empreinte ou du modulo.

## Tester une application qui utilise des indicateurs

### Tests unitaires : construire l'évaluateur

`FlagEvaluator` est un simple objet. Donnez-lui le fournisseur de configuration et un résolveur qui retourne le contexte
à tester :

```php
use Phil\FeatureFlagsBundle\Application\Flag\EvaluationContextResolver;
use Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\ConfiguredFlagProvider;

function flags(array $flags, ?EvaluationContext $context = null): FlagEvaluator
{
    $resolver = new class($context ?? EvaluationContext::anonymous()) implements EvaluationContextResolver {
        public function __construct(private EvaluationContext $context) {}
        public function current(): EvaluationContext { return $this->context; }
    };

    return new FlagEvaluator([ConfiguredFlagProvider::fromConfiguration($flags)], $resolver);
}

$mailer = new OrderMailer(flags(['order-cancellation-in-email' => true]));
$mailer = new OrderMailer(flags(['order-cancellation-in-email' => false]));
```

Testez les deux côtés de chaque indicateur : chaque indicateur double les chemins dans le code qu'il protège. Un
indicateur absent du tableau est désactivé (introuvable), ce que fait aussi la production pour un indicateur non
déclaré.

Pour tester un déploiement progressif ou une expérience de façon déterministe, choisissez des identifiants de contexte
dont vous connaissez les compartiments (`Bucket::of(FlagName::of('my-flag'), 'tenant-1')->value`), ou utilisez
`percentage: 0` et `percentage: 100`.

### Tests de noyau : configurer l'environnement de test

```yaml
# config/packages/test/phil_feature_flags.yaml
phil_feature_flags:
    context_resolver: phil_feature_flags.context_resolver.anonymous
    doctrine: false
    toggle: false
    admin: false
    flags:
        new-checkout: true
```

Les services sont privés ; dans un `KernelTestCase`, `static::getContainer()->get(FlagEvaluator::class)` atteint
l'évaluateur au moyen du conteneur de test. Pour basculer un indicateur dans un test sans base de données, configurez un
`state_store` en mémoire (un service `FlagStateSource` de l'environnement de test dont le test modifie la table), et
appelez `FlagEvaluator::reset()` après l'avoir modifié, puisque l'évaluateur mémoïse par requête.

### Tester des adaptateurs

- Un `EvaluationContextResolver` se teste en vérifiant le contexte qu'il construit pour un jeton ou un message donné.
- Un adaptateur `WritableFlagStore` ou `WritableFlagDefinitions` se teste pour son comportement en concurrence : un
  `compareAndSet()` avec une valeur attendue périmée retourne `false`, un second `insert()` retourne `false`, un
  `replace()` sur une ancienne révision retourne `false`, comme le fait `DoctrineStoreTest` pour le magasin en base de
  données.
- Un `ToggleAuditor` se teste pour vérifier qu'il lance une exception lorsqu'il ne peut pas écrire, puisque c'est ce qui
  arrête le changement.

### Faire échouer une construction pour des indicateurs expirés

```php
use Phil\FeatureFlagsBundle\Application\Flag\FlagCatalog;

public function testNoFlagHasOutstayedItsPurpose(): void
{
    $now = new \DateTimeImmutable();
    foreach (static::getContainer()->get(FlagCatalog::class)->entries() as $entry) {
        self::assertFalse($entry->declared->metadata->isExpiredAt($now), $entry->declared->name->value.' has expired.');
    }
}
```

Ou lancez `bin/console phil:feature-flags:list --fail-on-expired` en intégration continue ([console.md](console.md)).

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles. À partir de la
racine du monodépôt :

```sh
composer bench -- packages/feature-flags-bundle/tests/Benchmark
```
