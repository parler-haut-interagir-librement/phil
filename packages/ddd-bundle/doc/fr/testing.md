<!-- phil-dev source-sha256=1c41c34e9d1b13409ee5539ee8b788213ed75212e785947fabcfbd8802401ec2 -->
# Tests

## Comment le bundle est testé

| Suite | Répertoire | Exige | Ce qu'elle couvre |
|---|---|---|---|
| Unitaire | `tests/Unit` | rien | `Identifier`, `InvalidValue`, les deux adaptateurs Messenger sur un vrai `MessageBus` avec des *handlers* en mémoire, `IdentifierType` contre des objets de plateforme DBAL (PostgreSQL, SQL Server, MySQL 8, MariaDB, SQLite), et le câblage du bundle lu dans un `ContainerBuilder` non compilé |
| Fonctionnelle | `tests/Functional` | rien | Un vrai noyau avec Messenger et deux bus propres à l'application : une commande traitée et une requête répondue par les ports injectés par leur interface, chaque *handler* sur son bus par son seul marqueur (aussi tel que le liste `debug:messenger`), une erreur du domaine désenveloppée par le bus de commandes, l'exception d'un *handler* de requête désenveloppée par le bus de requêtes, et les erreurs de configuration à la construction |
| Intégration | `tests/Integration` | un serveur MySQL 8 ou PostgreSQL, sinon sautée | L'aller-retour d'un identifiant dans de vraies colonnes : insertion, recherche par identifiant, relecture, `NULL`, ordre de tri, une valeur étrangère refusée, et une colonne relue par introspection qui déclare le même SQL que le type |

La suite unitaire ne démarre aucun noyau et n'ouvre aucune connexion, ce qui la garde sous les tests de mutation.

### Tests fonctionnels

`tests/Functional` démarre `App\TestKernel`, avec des bus Messenger nommés `write.bus` et `read.bus`, reliés au bundle
par sa configuration, et des *handlers* enregistrés par l'autoconfiguration, sans étiquette ; les bus n'ont pas de
`doctrine_transaction`, puisque le noyau n'a pas Doctrine. Lancez-la avec
`vendor/bin/phpunit packages/ddd-bundle/tests/Functional`. Installée seule, là où FrameworkBundle n'est qu'une
dépendance de développement et où Symfony ne câble aucun répartiteur d'événements, elle est sautée.

### Tests d'intégration

Une classe de test par serveur, chacune sautée sauf si sa variable d'environnement contient une URL DBAL :

| Variable | Serveur et pilote | Groupe PHPUnit | Exemple |
|---|---|---|---|
| `PHIL_DDD_TEST_MYSQL_URL` | MySQL 8, `pdo_mysql` | `mysql` | `mysql://root:secret@127.0.0.1:3306/probe` |
| `PHIL_DDD_TEST_POSTGRESQL_URL` | PostgreSQL, `pdo_pgsql` | `postgresql` | `postgresql://user:secret@127.0.0.1:5432/probe` |

Chaque test crée la table `phil_ddd_identifier_probe` et la supprime, qu'il réussisse ou échoue. Les schémas d'URL
`mysql`, `postgresql` et `postgres` sont acceptés. Un serveur MySQL jetable suffit :

```bash
docker run --rm -d --name ddd-mysql -p 127.0.0.1:3306:3306 \
    -e MYSQL_ROOT_PASSWORD=secret -e MYSQL_DATABASE=probe mysql:8.4
PHIL_DDD_TEST_MYSQL_URL='mysql://root:secret@127.0.0.1:3306/probe' vendor/bin/phpunit --group mysql tests
docker stop ddd-mysql
```

## Lancer les tests

### Depuis la racine du monodépôt qui incube le bundle

Le bundle est installé dans la racine par un dépôt `path`, et ses tests s'exécutent sur le `vendor/` de la racine :

```bash
vendor/bin/phpunit packages/ddd-bundle/tests
```

Sans les deux variables, les huit cas d'intégration sont signalés comme sautés. Le `composer test:platforms` du
monodépôt (`tools/test-platforms.sh`) démarre un conteneur MySQL 8.4 jetable sur un port local au hasard, utilise un
PostgreSQL en marche (`PHIL_DDD_TEST_POSTGRESQL_URL`, ou la valeur par défaut du script), et lance
`tests/Integration` avec `--fail-on-skipped` : un serveur sauté y est donc un échec. `composer packages` installe le
bundle seul dans un répertoire neuf, vérifie ses dépendances déclarées avec `composer-dependency-analyser` contre
`composer-dependency-analyser.php`, puis lance ses tests.

### Installé seul

```bash
cd packages/ddd-bundle
composer install
vendor/bin/phpunit --no-configuration tests
```

`doctrine/dbal` et `phpunit/phpunit` viennent de `require-dev`.

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark` mesurent le type DBAL des identifiants, en convertissant de 1 à 1 000
valeurs hydratées sur une colonne binaire et sur une colonne UUID native. Lancez-les depuis la racine du monodépôt :

```bash
composer bench -- packages/ddd-bundle/tests/Benchmark
```

## Tester une application construite sur le bundle

### Handlers

Un *handler* est une simple classe : appelez `__invoke()` directement, avec des dépôts en mémoire.

```php
$orders = new InMemoryOrderRepository();
$handler = new PlaceOrderHandler($orders);

$orderId = OrderId::generate();
$handler(new PlaceOrder($orderId->toString(), $customerId, $lines));

self::assertNotNull($orders->find($orderId));
```

### Le code qui répartit

Remplacez le port par une doublure qui enregistre :

```php
final class RecordingCommandBus implements CommandBus
{
    /** @var list<Command> */
    public array $dispatched = [];

    public function dispatch(Command $command): void
    {
        $this->dispatched[] = $command;
    }
}
```

Pour exercer le vrai adaptateur sans noyau, construisez un bus Messenger à la main, comme le font les tests du bundle :

```php
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

$bus = new MessengerCommandBus(new MessageBus([
    new HandleMessageMiddleware(new HandlersLocator([PlaceOrder::class => [$handler]])),
]));
```

### Le câblage

Dans un test de noyau, vérifiez que chaque *handler* se trouve sur le bon bus avec `bin/console debug:messenger`, ou
faites vos assertions sur le conteneur compilé. Un *handler* qui n'apparaît pas sous son bus est le plus souvent un
service qui n'est pas autoconfiguré.

### Les types d'identifiants

Testez une sous-classe d'`IdentifierType` contre des objets de plateforme, comme le fait le bundle :

```php
$type = new OrderIdType();
$id = OrderId::generate();

self::assertTrue($id->equals($type->convertToPHPValue($type->convertToDatabaseValue($id, new MySQLPlatform()), new MySQLPlatform())));
```

Un test contre le vrai serveur compte quand l'application écrit du SQL brut contre des colonnes d'identifiants : c'est
dans la forme binaire que les requêtes écrites à la main se trompent.
