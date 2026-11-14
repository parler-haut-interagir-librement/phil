<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\TenancyBundle\Application\ActiveTenant;
use Phil\TenancyBundle\Application\Contract\ActiveTenantStore;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Infrastructure\Console\TenantOption;
use Phil\TenancyBundle\Infrastructure\Doctrine\ActiveTenantFilter;
use Phil\TenancyBundle\Infrastructure\Http\ResolveActiveTenant;
use Phil\TenancyBundle\Infrastructure\Http\SwitchActiveTenant;
use Phil\TenancyBundle\Infrastructure\Messenger\TenantMiddleware;
use Phil\TenancyBundle\Infrastructure\Session\SessionActiveTenantStore;
use Phil\TenancyBundle\Infrastructure\Twig\TenancyExtension;
use Phil\TenancyBundle\PhilTenancyBundle;
use Phil\TenancyBundle\Tests\Functional\App\HeaderActiveTenantStore;
use Phil\TenancyBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * What each `phil_tenancy` key registers, in the test application: the store, everything that needs the reachable
 * tenants, the request listener, the Doctrine filter and the console option. An unknown key stops the kernel
 * rather than being silently ignored.
 */
#[CoversClass(PhilTenancyBundle::class)]
final class ConfigurationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FunctionalTestCase::skipWithoutEventDispatcher();
    }

    public function testTheConfiguredApplicationHasEveryService(): void
    {
        $kernel = self::bootKernel();

        self::assertArrayHasKey('PhilTenancyBundle', $kernel->getBundles());
        self::assertInstanceOf(HeaderActiveTenantStore::class, $this->service(ActiveTenantStore::class));

        foreach ([TenantContext::class, ActiveTenant::class, SwitchActiveTenant::class, ResolveActiveTenant::class, TenantMiddleware::class, TenantOption::class] as $service) {
            self::assertTrue($this->has($service), $service);
        }

        // Twig is installed, but no TwigBundle collects the extension: an unused private service is removed.
        self::assertFalse($this->has(TenancyExtension::class));
        self::assertTrue($this->entityManager()->getFilters()->isEnabled(ActiveTenantFilter::NAME));
    }

    public function testTheSessionIsTheDefaultStore(): void
    {
        self::bootKernel(['configuration' => ['store' => 'session']]);

        self::assertInstanceOf(SessionActiveTenantStore::class, $this->service(ActiveTenantStore::class));
    }

    public function testWithoutReachableTenantsNothingResolves(): void
    {
        self::bootKernel(['configuration' => ['reachable_tenants' => null]]);

        foreach ([ActiveTenant::class, SwitchActiveTenant::class, ResolveActiveTenant::class] as $service) {
            self::assertFalse($this->has($service), $service);
        }

        self::assertTrue($this->has(TenantContext::class));
    }

    public function testTheListenerAndTheConsoleOptionCanBeTurnedOff(): void
    {
        self::bootKernel(['configuration' => ['request' => false, 'console' => false]]);

        self::assertFalse($this->has(ResolveActiveTenant::class));
        self::assertFalse($this->has(TenantOption::class));
        self::assertTrue($this->has(ActiveTenant::class));
    }

    public function testTheDoctrineFilterCanBeLeftToTheApplication(): void
    {
        self::bootKernel(['configuration' => ['doctrine_filter' => false]]);

        self::assertFalse($this->entityManager()->getFilters()->isEnabled(ActiveTenantFilter::NAME));
    }

    public function testAnUnknownKeyStopsTheKernel(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('Unrecognized option "default_tenant" under "phil_tenancy"');

        self::bootKernel(['configuration' => ['default_tenant' => 'retail']]);
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
        $configuration = $options['configuration'] ?? [];
        self::assertIsArray($configuration);

        /** @var array<string, mixed> $configuration */
        return new TestKernel('test', true, $configuration);
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    /**
     * A service of the booted kernel's test container, private ones included.
     */
    private function service(string $id): object
    {
        return self::getContainer()->get($id);
    }

    private function has(string $id): bool
    {
        return self::getContainer()->has($id);
    }
}
