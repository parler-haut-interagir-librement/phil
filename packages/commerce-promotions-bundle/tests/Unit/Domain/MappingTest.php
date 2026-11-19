<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Tests\Unit\Domain;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\JoinColumnMapping;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CommercePromotionsBundle\Domain\Model\Promotion;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionCode;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionCustomerUse;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionRedemption;
use Phil\CommercePromotionsBundle\Domain\Model\PromotionTranslation;
use Phil\CommercePromotionsBundle\Domain\Promotion\PromotionAction;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\LocaleDimensions\Locale;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for the promotions, their translations, codes, redemptions and uses
 * per customer.
 */
#[CoversClass(Promotion::class)]
#[CoversClass(PromotionCode::class)]
#[CoversClass(PromotionRedemption::class)]
#[CoversClass(PromotionCustomerUse::class)]
#[CoversClass(PromotionTranslation::class)]
final class MappingTest extends TestCase
{
    public function testThePromotionTables(): void
    {
        $promotions = $this->metadata(Promotion::class);
        $codes = $this->metadata(PromotionCode::class);

        self::assertSame('phil_commerce_promotion', $promotions->getTableName());
        self::assertSame(['code'], $promotions->getIdentifierFieldNames());
        self::assertSame([['columns' => ['active']]], array_values($promotions->table['indexes'] ?? []));
        self::assertSame([
            'active' => ['active', 'boolean', null, false, null],
            'uses' => ['uses', 'integer', null, false, null],
            'code' => ['code', 'string', 64, false, null],
            'label' => ['label', 'string', 255, false, null],
            'exclusive' => ['exclusive', 'boolean', null, false, null],
            'action' => ['action', 'string', 16, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'partsPerMillion' => ['parts_per_million', 'integer', null, false, null],
            'amountMinorUnits' => ['amount', 'bigint', null, false, null],
            'buyQuantity' => ['buy_quantity', 'smallint', null, false, null],
            'payQuantity' => ['pay_quantity', 'smallint', null, false, null],
            'groups' => ['bundle_groups', 'json', null, false, null],
            'minimumSubtotalMinorUnits' => ['minimum_subtotal', 'bigint', null, false, null],
            'skus' => ['skus', 'json', null, false, null],
            'excludedSkus' => ['excluded_skus', 'json', null, false, null],
            'channels' => ['channels', 'json', null, false, null],
            'customerGroups' => ['customer_groups', 'json', null, false, null],
            'deliveryMethods' => ['delivery_methods', 'json', null, false, null],
            'ownedSkus' => ['owned_skus', 'json', null, false, null],
            'requiresCode' => ['requires_code', 'boolean', null, false, null],
            'startsAt' => ['starts_at', 'datetime_utc_immutable', null, true, null],
            'endsAt' => ['ends_at', 'datetime_utc_immutable', null, true, null],
            'usageLimit' => ['usage_limit', 'integer', null, true, null],
            'perCustomerLimit' => ['per_customer_limit', 'integer', null, true, null],
            'after' => ['after_promotions', 'json', null, false, null],
            'incompatibleWith' => ['incompatible_with', 'json', null, false, null],
        ], $this->columns($promotions));
        self::assertSame(PromotionAction::class, $promotions->fieldMappings['action']->enumType);
        self::assertLessThanOrEqual(16, max(array_map(static fn (PromotionAction $action): int => \strlen($action->value), PromotionAction::cases())));

        self::assertSame('phil_commerce_promotion_code', $codes->getTableName());
        self::assertSame(['code'], $codes->getIdentifierFieldNames());
        self::assertSame([['columns' => ['promotion']]], array_values($codes->table['indexes'] ?? []));
        self::assertSame([
            'uses' => ['uses', 'integer', null, false, null],
            'code' => ['code', 'string', 64, false, null],
            'promotion' => ['promotion', 'string', 64, false, null],
            'usageLimit' => ['usage_limit', 'integer', null, true, null],
        ], $this->columns($codes));

        $promotion = Promotion::percentOff(str_repeat('p', 64), str_repeat('l', 255), 1, Currency::of('CAD'));
        $code = PromotionCode::of(str_repeat('C', 64), $promotion);
        self::assertSame($promotions->fieldMappings['code']->length, \strlen($promotion->code));
        self::assertSame($promotions->fieldMappings['label']->length, \strlen($promotion->label()));
        self::assertSame($codes->fieldMappings['code']->length, \strlen($code->code));
        self::assertSame($codes->fieldMappings['promotion']->length, \strlen($code->promotion));
    }

    public function testTheTranslationTable(): void
    {
        $metadata = $this->metadata(PromotionTranslation::class);

        self::assertSame('phil_commerce_promotion_translation', $metadata->getTableName());
        self::assertSame(['id'], $metadata->getIdentifierFieldNames());
        self::assertSame(['phil_commerce_promotion_translation_translatable_locale' => ['columns' => ['promotion', 'locale']]], $metadata->table['uniqueConstraints'] ?? []);
        self::assertSame([
            'locale' => ['locale', 'string', 12, false, null],
            'id' => ['id', 'integer', null, false, null],
            'label' => ['label', 'string', 255, false, null],
        ], $this->columns($metadata));
        $join = $metadata->getAssociationMapping('translatable');
        self::assertSame('sale', new PromotionTranslation(Promotion::percentOff('sale', 'Sale', 1, Currency::of('CAD')), Locale::fromString('fr'))->translatable()->code);
        self::assertInstanceOf(ManyToOneAssociationMapping::class, $join);
        self::assertSame(Promotion::class, $join->targetEntity);
        self::assertSame([['promotion', 'code', false, 'CASCADE']], array_map(static fn (JoinColumnMapping $column): array => [$column->name, $column->referencedColumnName, $column->nullable, $column->onDelete], $join->joinColumns));
    }

    public function testTheRedemptionAndCustomerTables(): void
    {
        $redemptions = $this->metadata(PromotionRedemption::class);
        $customers = $this->metadata(PromotionCustomerUse::class);

        self::assertSame('phil_commerce_promotion_redemption', $redemptions->getTableName());
        self::assertSame(['orderNumber', 'promotion'], $redemptions->getIdentifierFieldNames());
        self::assertSame([['columns' => ['promotion']]], array_values($redemptions->table['indexes'] ?? []));
        self::assertSame([
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'promotion' => ['promotion', 'string', 64, false, null],
            'code' => ['code', 'string', 64, true, null],
            'customer' => ['customer', 'string', 64, true, null],
        ], $this->columns($redemptions));

        self::assertSame('phil_commerce_promotion_customer_use', $customers->getTableName());
        self::assertSame(['promotion', 'customer'], $customers->getIdentifierFieldNames());
        self::assertSame([
            'uses' => ['uses', 'integer', null, false, null],
            'promotion' => ['promotion', 'string', 64, false, null],
            'customer' => ['customer', 'string', 64, false, null],
        ], $this->columns($customers));
    }

    public function testACustomerUseHoldsVisibleNamesThatFitItsColumns(): void
    {
        $longest = str_repeat('9', 64);
        $use = PromotionCustomerUse::of($longest, $longest);

        self::assertSame([$longest, $longest, 0], [$use->promotion, $use->customer, $use->uses()]);

        foreach ([['', 'c'], ['p', ''], [str_repeat('x', 65), 'c'], ['p', str_repeat('x', 65)], ["p\n", 'c'], ['p', "c\n"], ['p q', 'c'], ['p', 'c d']] as [$promotion, $customer]) {
            try {
                PromotionCustomerUse::of($promotion, $customer);
                self::fail('A customer use was accepted.');
            } catch (InvalidValue $invalidValue) {
                self::assertSame('A promotion and a customer are 1 to 64 visible ASCII characters.', $invalidValue->getMessage());
            }
        }
    }

    public function testARedemptionHoldsVisibleNamesThatFitItsColumns(): void
    {
        $longest = str_repeat('9', 64);
        $redemption = PromotionRedemption::of($longest, $longest, null);

        self::assertSame([$longest, $longest, null], [$redemption->orderNumber, $redemption->promotion, $redemption->code]);
        self::assertSame('SAVE-10', PromotionRedemption::of('1', 'sale', 'SAVE-10')->code);

        $refused = [
            ['', 'sale', null], ['1', str_repeat('x', 65), null], ['1', 'sale', 'A B'],
            [' 1', 'sale', null], ['1 ', 'sale', null], ["1\n", 'sale', null],
            ['1', "sale\n", null], ['1', 'sale', "SAVE\n"],
        ];

        foreach ($refused as [$order, $promotion, $code]) {
            try {
                PromotionRedemption::of($order, $promotion, $code);
                self::fail('A redemption was accepted.');
            } catch (InvalidValue $invalidValue) {
                self::assertSame('An order number, a promotion, a code and a customer are 1 to 64 visible ASCII characters.', $invalidValue->getMessage());
            }
        }
    }

    /**
     * @template T of object
     *
     * @param ClassMetadata<T> $metadata
     *
     * @return array<string, array{string, string, ?int, ?bool, mixed}>
     */
    private function columns(ClassMetadata $metadata): array
    {
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->options['fixed'] ?? null];
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
