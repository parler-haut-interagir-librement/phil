<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\TenancyBundle\Tests\Functional\App\Entity\Currency;
use Phil\TenancyBundle\Tests\Functional\App\Entity\Invoice;
use Phil\TenancyBundle\Tests\Functional\App\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The bundle in a kernel of its own (`App\TestKernel`), on a schema rebuilt for each test, with two invoices of
 * `retail`, one of `wholesale`, and two currencies every tenant shares.
 */
abstract class FunctionalTestCase extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true, static::configuration());
    }

    /**
     * What the test lays over the kernel's `phil_tenancy` configuration.
     *
     * @return array<string, mixed>
     */
    protected static function configuration(): array
    {
        return [];
    }

    #[Override]
    protected function setUp(): void
    {
        self::skipWithoutEventDispatcher();
        self::bootKernel();
        $entityManager = $this->entityManager();
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // Writes are not filtered: the rows of every tenant are inserted through the ORM.
        foreach ([new Invoice('retail', 'R-1'), new Invoice('retail', 'R-2'), new Invoice('wholesale', 'W-1'), new Currency('CAD'), new Currency('EUR')] as $entity) {
            $entityManager->persist($entity);
        }

        $entityManager->flush();
        $entityManager->clear();
    }

    /**
     * Symfony's `ServicesBundle` wires the event dispatcher only when `ContainerBuilder::willBeAvailable()` says
     * so, and it does not when `symfony/event-dispatcher` is installed for development only while
     * `symfony/dependency-injection` is not — the bundle installed on its own, with FrameworkBundle in its
     * `require-dev`. No kernel boots there, so the suite runs from an application that requires an HTTP kernel,
     * such as a monorepo root.
     */
    public static function skipWithoutEventDispatcher(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
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
        $request->headers->add($headers);

        return $kernel->handle($request);
    }

    /**
     * @return list<string>
     */
    protected function json(Response $response): array
    {
        self::assertSame(200, $response->getStatusCode());
        $decoded = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsList($decoded);
        self::assertContainsOnlyString($decoded);

        return $decoded;
    }
}
