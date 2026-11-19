<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Tests\Unit\Domain;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CommerceWholesaleBundle\Domain\Model\CustomerGroup;
use Phil\CommerceWholesaleBundle\Domain\Model\Membership;
use Phil\CommerceWholesaleBundle\Domain\Model\QuantityRule;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for the groups, the memberships and the quantity rules.
 */
#[CoversClass(CustomerGroup::class)]
#[CoversClass(Membership::class)]
#[CoversClass(QuantityRule::class)]
final class MappingTest extends TestCase
{
    public function testTheTables(): void
    {
        self::assertSame(['phil_commerce_wholesale_group', ['code'], [
            'active' => ['active', 'boolean', null, false, null],
            'code' => ['code', 'string', 64, false, null],
            'name' => ['name', 'string', 255, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'discountPartsPerMillion' => ['discount', 'integer', null, false, null],
            'minimumOrderMinorUnits' => ['minimum_order', 'bigint', null, false, null],
        ]], $this->table(CustomerGroup::class));

        self::assertSame(['phil_commerce_wholesale_membership', ['buyer'], [
            'buyer' => ['buyer', 'string', 255, false, null],
            'group' => ['customer_group', 'string', 64, false, null],
        ]], $this->table(Membership::class));
        self::assertSame([['columns' => ['customer_group']]], array_values($this->metadata(Membership::class)->table['indexes'] ?? []));

        $group = CustomerGroup::of(str_repeat('g', 64), str_repeat('n', 255), Currency::of('CAD'));
        $membership = Membership::of(str_repeat('b', 255), $group);
        self::assertSame($this->metadata(CustomerGroup::class)->fieldMappings['code']->length, \strlen($group->code));
        self::assertSame($this->metadata(Membership::class)->fieldMappings['buyer']->length, \strlen($membership->buyer));
        self::assertSame($this->metadata(Membership::class)->fieldMappings['group']->length, \strlen($membership->group()));

        self::assertSame(['phil_commerce_wholesale_quantity_rule', ['sku', 'group'], [
            'sku' => ['sku', 'string', 64, false, null],
            'group' => ['customer_group', 'string', 64, false, null],
            'minimum' => ['minimum', 'integer', null, false, null],
            'maximum' => ['maximum', 'integer', null, true, null],
            'increment' => ['increment', 'integer', null, false, null],
        ]], $this->table(QuantityRule::class));
        $rule = QuantityRule::of(str_repeat('s', 64), str_repeat('g', 64));
        self::assertSame($this->metadata(QuantityRule::class)->fieldMappings['sku']->length, \strlen($rule->sku));
        self::assertSame($this->metadata(QuantityRule::class)->fieldMappings['group']->length, \strlen((string) $rule->customerGroup));
    }

    /**
     * @param class-string $class
     *
     * @return array{string, array<int, string>, array<string, array{string, string, ?int, ?bool, mixed}>}
     */
    private function table(string $class): array
    {
        $metadata = $this->metadata($class);
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->options['fixed'] ?? null];
        }

        return [$metadata->getTableName(), $metadata->getIdentifierFieldNames(), $columns];
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
