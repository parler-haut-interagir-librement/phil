<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\FilterCollection;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Doctrine\ActiveTenantFilter;
use Phil\TenancyBundle\Infrastructure\Doctrine\DoctrineTenantActivation;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ScopedByField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineTenantActivation::class)]
final class DoctrineTenantActivationTest extends TestCase
{
    public function testTheTenantIsSetOnEveryManagerWithTheFilterEnabled(): void
    {
        [$first, $second, $disabled] = [$this->manager(true), $this->manager(true), $this->manager(false)];
        $activation = new DoctrineTenantActivation($this->registry($first, $disabled, self::createStub(ObjectManager::class), $second));

        $activation->activate(TenantId::fromString('retail'));

        self::assertSame("t0.tenant = 'retail'", $this->constraint($first));
        self::assertSame("t0.tenant = 'retail'", $this->constraint($second));
        self::assertFalse($disabled->getFilters()->isEnabled(ActiveTenantFilter::NAME));
    }

    /**
     * Leaving builds a fresh filter: it carries no tenant and matches nothing, and stays enabled. A filter disabled for
     * cross-tenant work, and left so, is enabled again: the next unit of work of a long-running worker never starts
     * unfiltered. A manager that does not know the filter is left alone.
     */
    public function testLeavingTheTenantFailsClosed(): void
    {
        [$manager, $disabled, $unknown] = [$this->manager(true), $this->manager(false), $this->manager(false, false)];
        $activation = new DoctrineTenantActivation($this->registry($manager, $unknown, self::createStub(ObjectManager::class), $disabled));
        $activation->activate(TenantId::fromString('retail'));

        $activation->deactivate();

        self::assertTrue($manager->getFilters()->isEnabled(ActiveTenantFilter::NAME));
        self::assertSame('1 = 0', $this->constraint($manager));
        self::assertTrue($disabled->getFilters()->isEnabled(ActiveTenantFilter::NAME));
        self::assertSame('1 = 0', $this->constraint($disabled));
        self::assertSame([], $unknown->getFilters()->getEnabledFilters());

        $activation->activate(TenantId::fromString('wholesale'));
        self::assertSame("t0.tenant = 'wholesale'", $this->constraint($disabled), 'the next tenant reaches it');
    }

    public function testWithoutDoctrineThereIsNothingToActivate(): void
    {
        $activation = new DoctrineTenantActivation();

        $activation->activate(TenantId::fromString('retail'));
        $activation->deactivate();

        $this->expectNotToPerformAssertions();
    }

    private function constraint(EntityManagerInterface $manager): string
    {
        $filter = $manager->getFilters()->getFilter(ActiveTenantFilter::NAME);
        self::assertInstanceOf(ActiveTenantFilter::class, $filter);
        $metadata = new ClassMetadata(ScopedByField::class);
        $metadata->mapField(['fieldName' => 'tenantRef', 'columnName' => 'tenant', 'type' => 'string']);

        return $filter->addFilterConstraint($metadata, 't0');
    }

    private function manager(bool $enabled, bool $known = true): EntityManagerInterface
    {
        $connection = self::createStub(Connection::class);
        $connection->method('quote')->willReturnCallback(static fn (string $value): string => "'".$value."'");

        $configuration = new Configuration();
        if ($known) {
            $configuration->addFilter(ActiveTenantFilter::NAME, ActiveTenantFilter::class);
        }

        $manager = self::createStub(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($connection);
        $manager->method('getConfiguration')->willReturn($configuration);
        // The collection reads the configuration when it is built.
        $filters = new FilterCollection($manager);
        $manager->method('getFilters')->willReturn($filters);

        if ($enabled) {
            $filters->enable(ActiveTenantFilter::NAME);
        }

        return $manager;
    }

    private function registry(ObjectManager ...$managers): ManagerRegistry
    {
        $registry = self::createStub(ManagerRegistry::class);
        $registry->method('getManagers')->willReturn($managers);

        return $registry;
    }
}
