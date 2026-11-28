<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Phil\Measurement\UnitRegistry;
use Phil\MeasurementBundle\Infrastructure\Doctrine\MeasurementType;
use PHPUnit\Framework\TestCase;

/**
 * The column type on a real server: a table created from the type's declaration, measurements written and read back
 * exactly. The type is given to each statement, so that the static registry of Doctrine is left as it is.
 */
abstract class DatabaseTestCase extends TestCase
{
    abstract protected function url(): string;

    public function testMeasurementsAreWrittenAndReadBackExactly(): void
    {
        $registry = UnitRegistry::standard();
        $type = new MeasurementType($registry);
        $connection = $this->connection();
        $platform = $connection->getDatabasePlatform();
        $connection->executeStatement('DROP TABLE IF EXISTS phil_measurement_test');
        $connection->executeStatement('CREATE TABLE phil_measurement_test (weight '.$type->getSQLDeclaration([], $platform).' NULL)');

        $values = [$registry->measurement('1', 'kg')->convertTo($registry->unit('[lb_av]')), $registry->measurement('-40', '[degF]'), null];

        foreach ($values as $value) {
            $connection->insert('phil_measurement_test', ['weight' => $value], ['weight' => $type]);
        }

        $read = array_map(
            static fn (mixed $value): ?string => $type->convertToPHPValue($value, $platform)?->toString(),
            $connection->fetchFirstColumn('SELECT weight FROM phil_measurement_test'),
        );

        self::assertSame(['2.20462262184877581 [lb_av]', '-40 [degF]', null], $read);
        $connection->executeStatement('DROP TABLE phil_measurement_test');
    }

    private function connection(): Connection
    {
        /** @var array{driver: 'pdo_mysql'|'pdo_pgsql'|'pdo_sqlite'} $parameters */
        $parameters = new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'sqlite' => 'pdo_sqlite'])->parse($this->url());

        return DriverManager::getConnection($parameters);
    }
}
