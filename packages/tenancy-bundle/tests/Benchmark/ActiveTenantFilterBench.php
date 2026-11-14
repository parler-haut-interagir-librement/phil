<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Benchmark;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Generator;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Doctrine\ActiveTenantFilter;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\InheritanceRoot;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\NotScoped;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ScopedByField;
use Phil\TenancyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\UnscopedSubclass;

/**
 * Doctrine asks the filter for a constraint on every entity of every SQL it generates, persister loads and lazy
 * loads included: each call reflects the class, instantiates its attribute and, for an unscoped root, reflects every
 * subclass too.
 *
 * @Groups({"tenancy", "doctrine"})
 */
final readonly class ActiveTenantFilterBench
{
    private ActiveTenantFilter $filter;

    /**
     * @var ClassMetadata<ScopedByField>
     */
    private ClassMetadata $scoped;

    /**
     * @var ClassMetadata<NotScoped>
     */
    private ClassMetadata $unscoped;

    /**
     * @var array<int, ClassMetadata<InheritanceRoot>> by the number of subclasses
     */
    private array $hierarchies;

    public function __construct()
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([\dirname(__DIR__).'/Functional/App/Entity']));
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);

        $this->filter = new ActiveTenantFilter($entityManager);
        $this->filter->activate(TenantId::fromString('retail'));

        $this->scoped = new ClassMetadata(ScopedByField::class);
        $this->scoped->mapField(['fieldName' => 'tenantRef', 'columnName' => 'tenant_ref', 'type' => 'string']);

        $this->unscoped = new ClassMetadata(NotScoped::class);

        $hierarchies = [];

        foreach ([1, 10, 50] as $count) {
            $root = new ClassMetadata(InheritanceRoot::class);
            $root->setSubclasses(array_fill(0, $count, UnscopedSubclass::class));
            $hierarchies[$count] = $root;
        }

        $this->hierarchies = $hierarchies;
    }

    /**
     * One SQL statement: the filter is asked once per entity it reads, half of them scoped.
     *
     * @ParamProviders("statements")
     *
     * @param array{entities: int} $params
     */
    public function benchStatement(array $params): void
    {
        for ($i = 0; $i < $params['entities']; ++$i) {
            $this->filter->addFilterConstraint(0 === $i % 2 ? $this->scoped : $this->unscoped, 't'.$i);
        }
    }

    /**
     * @ParamProviders("hierarchies")
     *
     * @param array{subclasses: int} $params
     */
    public function benchUnscopedRoot(array $params): void
    {
        $this->filter->addFilterConstraint($this->hierarchies[$params['subclasses']], 't0');
    }

    /**
     * @return Generator<string, array{entities: int}>
     */
    public function statements(): Generator
    {
        yield 'find (1 entity)' => ['entities' => 1];
        yield 'query with 4 joins (5 entities)' => ['entities' => 5];
        yield 'report with 19 joins (20 entities)' => ['entities' => 20];
    }

    /**
     * @return Generator<string, array{subclasses: int}>
     */
    public function hierarchies(): Generator
    {
        yield '1 subclass' => ['subclasses' => 1];
        yield '10 subclasses' => ['subclasses' => 10];
        yield '50 subclasses' => ['subclasses' => 50];
    }
}
