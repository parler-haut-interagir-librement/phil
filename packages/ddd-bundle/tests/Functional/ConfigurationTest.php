<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Functional;

use Override;
use Phil\DddBundle\PhilDddBundle;
use Phil\DddBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The application booted on configurations the bundle refuses: the kernel does not start, rather than a command
 * reaching the wrong bus.
 */
#[CoversClass(PhilDddBundle::class)]
final class ConfigurationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FunctionalTestCase::skipWithoutEventDispatcher();
    }

    public function testABusIdThatIsNotAStringStopsTheKernel(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"phil_ddd.command_bus" must be a service id.');

        self::bootKernel(['configuration' => ['command_bus' => 5, 'query_bus' => TestKernel::QUERY_BUS]]);
    }

    public function testAnEmptyBusIdStopsTheKernel(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_ddd.query_bus');

        self::bootKernel(['configuration' => ['command_bus' => TestKernel::COMMAND_BUS, 'query_bus' => '']]);
    }

    public function testAnUnknownKeyStopsTheKernel(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('Unrecognized option "event_bus" under "phil_ddd"');

        self::bootKernel(['configuration' => [...TestKernel::CONFIGURATION, 'event_bus' => 'event.bus']]);
    }

    /**
     * The defaults, `command.bus` and `query.bus`, name no bus of this application: Messenger refuses the handler
     * tags the markers added.
     */
    public function testABusIdThatNamesNoServiceStopsTheKernel(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('bus "command.bus" specified on the tag "messenger.message_handler" does not exist');

        self::bootKernel(['configuration' => []]);
    }

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
        $configuration = $options['configuration'] ?? TestKernel::CONFIGURATION;
        self::assertIsArray($configuration);

        /** @var array<string, mixed> $configuration */
        return new TestKernel('test', true, $configuration);
    }
}
