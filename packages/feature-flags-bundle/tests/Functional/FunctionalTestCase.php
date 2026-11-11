<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\FeatureFlagsBundle\Infrastructure\Doctrine\FlagTables;
use Phil\FeatureFlagsBundle\Tests\Functional\App\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * The bundle in a kernel of its own (`App\TestKernel`), with the two flag tables rebuilt for each test. Each
 * request goes through the kernel as a worker would serve it: the services are reset between two requests.
 */
abstract class FunctionalTestCase extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $connection = $this->connection();
        $schemaManager = $connection->createSchemaManager();

        foreach ([FlagTables::DEFINITIONS, FlagTables::STATES] as $table) {
            if ($schemaManager->tablesExist([$table])) {
                $schemaManager->dropTable($table);
            }
        }

        foreach (FlagTables::configure(new Schema())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    protected function connection(): Connection
    {
        return $this->service('doctrine.dbal.default_connection', Connection::class);
    }

    /**
     * A service of the test kernel's container.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $form
     */
    protected function request(string $method, string $path, array $headers = [], array $form = []): Response
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);

        $request = Request::create($path, $method, $form);

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        $response = $kernel->handle($request);

        if ($kernel instanceof TerminableInterface) {
            $kernel->terminate($request, $response);
        }

        return $response;
    }

    /**
     * A form of the admin, posted by the operator from the admin itself.
     *
     * @param array<string, string> $form
     */
    protected function post(string $path, array $form, string $operator = 'operator'): Response
    {
        return $this->request('POST', $path, ['X-Operator' => $operator, 'Sec-Fetch-Site' => 'same-origin'], $form);
    }
}
