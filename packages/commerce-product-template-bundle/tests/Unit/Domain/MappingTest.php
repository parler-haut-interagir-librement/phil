<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Domain;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceProductTemplateBundle\Domain\Model\GroupMember;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductDesign;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductGroup;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductTemplate;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\Shirts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for templates, designs, groups and their members.
 */
#[CoversClass(ProductTemplate::class)]
#[CoversClass(ProductDesign::class)]
#[CoversClass(ProductGroup::class)]
#[CoversClass(GroupMember::class)]
final class MappingTest extends TestCase
{
    public function testTheTemplateTable(): void
    {
        // Built once, so that the constructor whose parameters carry the columns runs under this test.
        Shirts::productTemplate();
        $metadata = $this->metadata(ProductTemplate::class);

        self::assertSame('phil_commerce_product_template', $metadata->getTableName());
        self::assertSame(['code'], $metadata->getIdentifierFieldNames());
        self::assertSame([
            'definition' => ['definition', 'json', null, false],
            'options' => ['options', 'json', null, false],
            'code' => ['code', 'string', 32, false],
            'kind' => ['kind', 'string', 16, false],
            'taxCategory' => ['tax_category', 'string', 64, false],
        ], $this->columns($metadata));
        self::assertSame(ProductKind::class, $metadata->fieldMappings['kind']->enumType);
    }

    public function testTheDesignTable(): void
    {
        Shirts::productDesign();
        $metadata = $this->metadata(ProductDesign::class);

        self::assertSame('phil_commerce_product_design', $metadata->getTableName());
        self::assertSame(['code'], $metadata->getIdentifierFieldNames());
        self::assertSame([
            'definition' => ['definition', 'json', null, false],
            'options' => ['options', 'json', null, false],
            'printLayout' => ['print_layout', 'json', null, false],
            'code' => ['code', 'string', 32, false],
        ], $this->columns($metadata));
        self::assertSame(['default' => '[]'], $metadata->fieldMappings['printLayout']->options);
    }

    public function testTheGroupTables(): void
    {
        ProductGroup::of('NAQ001', 'unisex');
        GroupMember::of('NAQ001FOMBK', 'NAQ001:unisex', ['size' => 'M'], 'F');
        $groups = $this->metadata(ProductGroup::class);

        self::assertSame('phil_commerce_product_group', $groups->getTableName());
        self::assertSame(['id'], $groups->getIdentifierFieldNames());
        self::assertSame([['columns' => ['design', 'template']]], array_values($groups->table['uniqueConstraints'] ?? []));
        self::assertSame([
            'active' => ['active', 'boolean', null, false],
            'id' => ['id', 'string', 65, false],
            'design' => ['design', 'string', 32, false],
            'template' => ['template', 'string', 32, false],
        ], $this->columns($groups));

        $members = $this->metadata(GroupMember::class);

        self::assertSame('phil_commerce_product_group_member', $members->getTableName());
        self::assertSame(['sku'], $members->getIdentifierFieldNames());
        self::assertSame([['columns' => ['group_id']]], array_values($members->table['indexes'] ?? []));
        self::assertSame([
            'active' => ['active', 'boolean', null, false],
            'mockups' => ['mockups', 'json', null, false],
            'sku' => ['sku', 'string', 64, false],
            'groupId' => ['group_id', 'string', 65, false],
            'values' => ['axis_values', 'json', null, false],
            'language' => ['language', 'string', 8, true],
        ], $this->columns($members));
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return array<string, array{string, string, int|null, bool|null}>
     */
    private function columns(ClassMetadata $metadata): array
    {
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable];
        }

        return $columns;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     */
    private function metadata(string $class): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 3).'/src/Domain/Model'])->loadMetadataForClass($class, $metadata);

        return $metadata;
    }
}
