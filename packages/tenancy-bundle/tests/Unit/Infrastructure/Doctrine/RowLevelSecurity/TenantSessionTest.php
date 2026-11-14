<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\RowLevelSecurity;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\RowLevelSecurityConfig;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\TenantSession;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\TenantSessionDriver;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\TenantSessionMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantSession::class)]
#[CoversClass(TenantSessionMiddleware::class)]
#[CoversClass(TenantSessionDriver::class)]
final class TenantSessionTest extends TestCase
{
    /** @var list<array{string, list<array{int|string, mixed, ParameterType}>}> */
    private array $statements = [];

    public function testAConnectionOpenedOutsideATenantIsLeftUnset(): void
    {
        new TenantSession(new RowLevelSecurityConfig())->attach($this->connection('a'));

        self::assertSame([], $this->statements);
    }

    public function testEveryOpenConnectionFollowsTheTenantInAndOut(): void
    {
        $session = new TenantSession(new RowLevelSecurityConfig('app.tenant'));
        $first = $this->connection('first');
        $second = $this->connection('second');
        $session->attach($first);
        $session->attach($second);

        $session->activate(TenantId::fromString("retail' --"));
        $session->deactivate();

        self::assertSame([
            ['first', [[1, 'app.tenant', ParameterType::STRING], [2, "retail' --", ParameterType::STRING]]],
            ['second', [[1, 'app.tenant', ParameterType::STRING], [2, "retail' --", ParameterType::STRING]]],
            ['first', [[1, 'app.tenant', ParameterType::STRING], [2, '', ParameterType::STRING]]],
            ['second', [[1, 'app.tenant', ParameterType::STRING], [2, '', ParameterType::STRING]]],
        ], $this->statements);
    }

    public function testAConnectionOpenedInsideATenantTakesIt(): void
    {
        $session = new TenantSession(new RowLevelSecurityConfig());
        $session->activate(TenantId::fromString('retail'));

        $session->attach($this->connection('late'));

        self::assertSame([['late', [[1, 'phil.tenant_id', ParameterType::STRING], [2, 'retail', ParameterType::STRING]]]], $this->statements);
    }

    public function testADroppedConnectionIsForgotten(): void
    {
        $session = new TenantSession(new RowLevelSecurityConfig());
        $session->attach($this->connection('dropped'));
        gc_collect_cycles();

        $session->activate(TenantId::fromString('retail'));

        self::assertSame([], $this->statements);
    }

    public function testTheMiddlewareHandsEachNewConnectionToTheSession(): void
    {
        $session = new TenantSession(new RowLevelSecurityConfig());
        $session->activate(TenantId::fromString('retail'));

        $connection = $this->connection('opened');
        $driver = self::createMock(Driver::class);
        $driver->expects(self::once())->method('connect')->with(['dbname' => 'app'])->willReturn($connection);

        $wrapped = new TenantSessionMiddleware($session)->wrap($driver);

        self::assertInstanceOf(TenantSessionDriver::class, $wrapped);
        self::assertSame($connection, $wrapped->connect(['dbname' => 'app']));
        self::assertSame([['opened', [[1, 'phil.tenant_id', ParameterType::STRING], [2, 'retail', ParameterType::STRING]]]], $this->statements);
    }

    private function connection(string $name): Connection
    {
        $connection = self::createStub(Connection::class);
        $connection->method('prepare')->willReturnCallback(function (string $sql) use ($name): Statement {
            self::assertSame('SELECT set_config(?, ?, false)', $sql);
            $bound = [];
            $statement = self::createStub(Statement::class);
            $statement->method('bindValue')->willReturnCallback(static function (int|string $param, mixed $value, ParameterType $type) use (&$bound): void {
                $bound[] = [$param, $value, $type];
            });
            $statement->method('execute')->willReturnCallback(function () use ($name, &$bound): Result {
                $this->statements[] = [$name, $bound];
                $result = self::createMock(Result::class);
                $result->expects(self::once())->method('free');

                return $result;
            });

            return $statement;
        });

        return $connection;
    }
}
