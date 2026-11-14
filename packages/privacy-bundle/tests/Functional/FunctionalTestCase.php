<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Functional;

use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Override;
use Phil\PrivacyBundle\Tests\Functional\App\TestKernel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The bundle in a kernel of its own (`App\TestKernel`), whose Monolog keeps every record in memory, emptied before
 * each test.
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
        self::skipWithoutEventDispatcher();
        self::bootKernel();
        $this->handler()->clear();
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

    protected function logger(): LoggerInterface
    {
        return $this->service('logger', LoggerInterface::class);
    }

    /**
     * The records as the handler received them, after every processor ran.
     *
     * @return list<LogRecord>
     */
    protected function records(): array
    {
        return array_values($this->handler()->getRecords());
    }

    protected function request(string $path): Response
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);

        return $kernel->handle(Request::create($path));
    }

    private function handler(): TestHandler
    {
        return $this->service('monolog.handler.memory', TestHandler::class);
    }
}
