<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Tests\Unit\Domain;

use Doctrine\ORM\EntityManager;
use Phil\Resource\ResourceReference;
use Phil\Slug\Slug;
use Phil\TaxonomyBundle\Domain\Model\Assignment;
use Phil\TaxonomyBundle\Domain\Model\Category;
use Phil\TaxonomyBundle\Domain\Model\CategoryTranslation;
use Phil\TaxonomyBundle\Domain\Model\Tag;
use Phil\TaxonomyBundle\Tests\Unit\Fixtures\Database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The tables an application's migrations create for the taxonomy.
 */
#[CoversClass(Tag::class)]
#[CoversClass(Category::class)]
#[CoversClass(CategoryTranslation::class)]
#[CoversClass(Assignment::class)]
final class MappingTest extends TestCase
{
    public function testTheTables(): void
    {
        $entityManager = Database::entityManager();
        self::assertSame(['phil_taxonomy_tag', ['id' => ['id', 'string', 36, false, true], 'name' => ['name', 'string', 100, false, null], 'slug' => ['slug', 'string', 128, false, null]], [['slug']], []], $this->describe($entityManager, Tag::class));
        self::assertSame(['phil_taxonomy_category', ['id' => ['id', 'string', 36, false, true], 'key' => ['category_key', 'string', 128, false, null], 'path' => ['path', 'string', 420, false, null], 'position' => ['position', 'integer', null, false, null], 'parent' => ['parent', Category::class, true, 'RESTRICT']], [['category_key']], [['path']]], $this->describe($entityManager, Category::class));
        self::assertSame(['phil_taxonomy_category_translation', ['id' => ['id', 'string', 36, false, true], 'locale' => ['locale', 'string', 16, false, null], 'name' => ['name', 'string', 150, false, null], 'description' => ['description', 'text', null, true, null], 'category' => ['category', Category::class, false, 'CASCADE']], [['category', 'locale']], []], $this->describe($entityManager, CategoryTranslation::class));
        self::assertSame(['phil_taxonomy_assignment', ['id' => ['id', 'string', 36, false, true], 'termType' => ['term_type', 'string', 8, false, null], 'termId' => ['term_id', 'string', 36, false, null], 'resourceKind' => ['resource_kind', 'string', 64, false, null], 'resourceId' => ['resource_id', 'string', 255, false, null]], [['term_type', 'term_id', 'resource_kind', 'resource_id']], [['resource_kind', 'resource_id']]], $this->describe($entityManager, Assignment::class));
        self::assertSame(36, \strlen(Assignment::ofTag(Tag::named('x', Slug::fromString('x')), ResourceReference::of('page', '1'))->id));
        self::assertSame(36, \strlen(CategoryTranslation::of(Category::create(Slug::fromString('x')), 'fr', 'x')->id));
        self::assertSame(36, \strlen(Category::create(Slug::fromString('x'))->id));
        self::assertSame(36, \strlen(Tag::named('x', Slug::fromString('x'))->id));
    }

    /**
     * @param class-string $class
     *
     * @return array{string, array<string, list<mixed>>, list<mixed>, list<mixed>}
     */
    private function describe(EntityManager $entityManager, string $class): array
    {
        $metadata = $entityManager->getClassMetadata($class);
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->options['fixed'] ?? null];
        }

        foreach ($metadata->associationMappings as $field => $mapping) {
            $join = $mapping->joinColumns[0] ?? null;
            $columns[$field] = [$join?->name, $mapping->targetEntity, $join?->nullable, $join?->onDelete];
        }

        $unique = [];

        foreach ($metadata->table['uniqueConstraints'] ?? [] as $constraint) {
            self::assertIsArray($constraint);
            $unique[] = $constraint['columns'] ?? [];
        }

        $indexes = [];

        foreach ($metadata->table['indexes'] ?? [] as $index) {
            self::assertIsArray($index);
            $indexes[] = $index['columns'] ?? [];
        }

        return [$metadata->getTableName(), $columns, $unique, $indexes];
    }
}
