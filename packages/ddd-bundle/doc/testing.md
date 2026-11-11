# Testing

## How the bundle is tested

| Suite | Directory | Needs | What it covers |
|---|---|---|---|
| Unit | `tests/Unit` | nothing | `Identifier`, `InvalidValue`, both Messenger adapters on a real `MessageBus` with in-memory handlers, `IdentifierType` against DBAL platform objects (PostgreSQL, SQL Server, MySQL 8, MariaDB, SQLite), and the bundle's wiring read from an uncompiled `ContainerBuilder` |
| Functional | `tests/Functional` | nothing | A real kernel with Messenger and two buses of the application's own: a command handled and a query answered through the ports autowired by their interface, each handler on its bus by its marker alone (also as `debug:messenger` lists it), a domain error unwrapped from the command bus, a query handler's exception unwrapped from the query bus, and the build-time configuration errors |
| Integration | `tests/Integration` | a MySQL 8 or PostgreSQL server, else skipped | The round trip of an identifier through real columns: insert, find by identifier, read back, `NULL`, sort order, a foreign value refused, and an introspected column that declares the same SQL as the type |

The unit suite boots no kernel and opens no connection, which keeps it under mutation testing.

### Functional tests

`tests/Functional` boots `App\TestKernel`, with Messenger buses named `write.bus` and `read.bus` and joined to the
bundle by its configuration, and handlers registered with autoconfiguration and no tag; the buses carry no
`doctrine_transaction`, since the kernel has no Doctrine. Run it with
`vendor/bin/phpunit packages/ddd-bundle/tests/Functional`; installed on its own, where FrameworkBundle is only a
development dependency and Symfony wires no event dispatcher, it is skipped.

### Integration tests

One test class per server, each skipped unless its environment variable holds a DBAL URL:

| Variable | Server and driver | PHPUnit group | Example |
|---|---|---|---|
| `PHIL_DDD_TEST_MYSQL_URL` | MySQL 8, `pdo_mysql` | `mysql` | `mysql://root:secret@127.0.0.1:3306/probe` |
| `PHIL_DDD_TEST_POSTGRESQL_URL` | PostgreSQL, `pdo_pgsql` | `postgresql` | `postgresql://user:secret@127.0.0.1:5432/probe` |

Each test creates the table `phil_ddd_identifier_probe` and drops it whether it passes or fails. The URL schemes
`mysql`, `postgresql` and `postgres` are accepted. A throwaway MySQL server is enough:

```bash
docker run --rm -d --name ddd-mysql -p 127.0.0.1:3306:3306 \
    -e MYSQL_ROOT_PASSWORD=secret -e MYSQL_DATABASE=probe mysql:8.4
PHIL_DDD_TEST_MYSQL_URL='mysql://root:secret@127.0.0.1:3306/probe' vendor/bin/phpunit --group mysql tests
docker stop ddd-mysql
```

## Running the tests

### From the root of the monorepo that incubates the bundle

The bundle is installed into the root through a `path` repository, and its tests run on the root `vendor/`:

```bash
vendor/bin/phpunit packages/ddd-bundle/tests
```

Without the two variables, the eight integration cases are reported as skipped. The monorepo's
`composer test:platforms` (`tools/test-platforms.sh`) starts a throwaway MySQL 8.4 container on a random local
port, uses a running PostgreSQL (`PHIL_DDD_TEST_POSTGRESQL_URL`, or the default of the script), and runs
`tests/Integration` with `--fail-on-skipped`, so a skipped server is a failure there. `composer packages`
installs the bundle alone in a fresh directory, checks its declared dependencies with `composer-dependency-analyser`
against `composer-dependency-analyser.php`, then runs its tests.

### Installed alone

```bash
cd packages/ddd-bundle
composer install
vendor/bin/phpunit --no-configuration tests
```

`doctrine/dbal` and `phpunit/phpunit` come from `require-dev`.

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the identifier DBAL type, converting 1 to 1 000 hydrated values on a binary and on a native UUID column. Run them from the monorepo root:

```bash
composer bench -- packages/ddd-bundle/tests/Benchmark
```

## Testing an application built on the bundle

### Handlers

A handler is a plain class: call `__invoke()` directly with in-memory repositories.

```php
$orders = new InMemoryOrderRepository();
$handler = new PlaceOrderHandler($orders);

$orderId = OrderId::generate();
$handler(new PlaceOrder($orderId->toString(), $customerId, $lines));

self::assertNotNull($orders->find($orderId));
```

### Code that dispatches

Replace the port with a recording double:

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

To exercise the real adapter without a kernel, build a Messenger bus by hand, as the bundle's own tests do:

```php
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

$bus = new MessengerCommandBus(new MessageBus([
    new HandleMessageMiddleware(new HandlersLocator([PlaceOrder::class => [$handler]])),
]));
```

### Wiring

In a kernel test, check that each handler sits on the right bus with `bin/console debug:messenger`, or assert on
the compiled container. A handler that does not appear under its bus is usually a service that is not
autoconfigured.

### Identifier types

Unit-test a subclass of `IdentifierType` against platform objects, as the bundle does:

```php
$type = new OrderIdType();
$id = OrderId::generate();

self::assertTrue($id->equals($type->convertToPHPValue($type->convertToDatabaseValue($id, new MySQLPlatform()), new MySQLPlatform())));
```

A test against the real server matters when the application writes raw SQL against identifier columns, because
the binary form is where hand-written queries go wrong.
