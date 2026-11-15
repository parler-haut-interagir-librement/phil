<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Receipt;
use Phil\CommerceBundle\Domain\Receipt\DocumentKind;
use Phil\CommerceBundle\Domain\ValueObject\Issuer;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Infrastructure\Doctrine\Entity\DocumentSequence;
use Phil\CommerceBundle\PhilCommerceBundle;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for `phil_commerce_receipt` and `phil_commerce_document_sequence`.
 */
#[CoversClass(Receipt::class)]
#[CoversClass(DocumentSequence::class)]
final class ReceiptMappingTest extends TestCase
{
    public function testTheReceiptIsOneRowKeyedByItsNumber(): void
    {
        $metadata = $this->metadata(Receipt::class, 'Domain/Model');
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->options['fixed'] ?? null];
        }

        self::assertSame('phil_commerce_receipt', $metadata->getTableName());
        self::assertSame(['number'], $metadata->getIdentifierFieldNames());
        self::assertSame([['columns' => ['order_number']], ['columns' => ['credited_receipt']]], array_values($metadata->table['indexes'] ?? []));
        self::assertSame([
            'currencyCode' => ['currency', 'string', 3, false, true],
            'issuerData' => ['issuer', 'json', null, false, null],
            'number' => ['number', 'string', 32, false, null],
            'kind' => ['kind', 'string', 16, false, null],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'creditedReceipt' => ['credited_receipt', 'string', 32, true, null],
            'issuedAt' => ['issued_at', 'datetime_immutable', null, false, null],
            'lines' => ['lines', 'phil_commerce_order_lines', null, false, null],
            'adjustments' => ['adjustments', 'phil_commerce_adjustments', null, false, null],
        ], $columns);
        self::assertSame(DocumentKind::class, $metadata->fieldMappings['kind']->enumType);
    }

    /**
     * The longest number and order number fit their columns.
     */
    public function testWhatAReceiptHoldsFitsItsColumns(): void
    {
        $cad = Money::of(1, Currency::of('CAD'));
        $order = Order::open(str_repeat('9', 64), Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'x'), 'X', $cad, 'goods'), 1, $cad);
        $order->place(new DateTimeImmutable());

        $receipt = Receipt::issue(str_repeat('R', 32), $order, Issuer::of('Shop'), new DateTimeImmutable());
        $mappings = $this->metadata(Receipt::class, 'Domain/Model')->fieldMappings;

        self::assertSame($mappings['number']->length, \strlen($receipt->number));
        self::assertSame($mappings['number']->length, PhilCommerceBundle::DOCUMENT_NUMBER_LENGTH);
        self::assertSame($mappings['orderNumber']->length, \strlen($receipt->orderNumber));
    }

    public function testASequenceIsOneRowPerSeries(): void
    {
        $metadata = $this->metadata(DocumentSequence::class, 'Infrastructure/Doctrine/Entity');

        self::assertSame('phil_commerce_document_sequence', $metadata->getTableName());
        self::assertSame(['series'], $metadata->getIdentifierFieldNames());
        self::assertSame(['last_number', 'bigint'], [$metadata->fieldMappings['last']->columnName, $metadata->fieldMappings['last']->type]);
        self::assertSame(['series', 'string', 16], [$metadata->fieldMappings['series']->columnName, $metadata->fieldMappings['series']->type, $metadata->fieldMappings['series']->length]);

        $sequence = new DocumentSequence('credit_note');
        self::assertSame($metadata->fieldMappings['series']->length, \strlen(str_pad($sequence->series, 16)));
        self::assertSame(1, $sequence->next());
        self::assertSame(2, $sequence->next());
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return ClassMetadata<T>
     */
    private function metadata(string $class, string $dir): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 4).'/src/'.$dir])->loadMetadataForClass($class, $metadata);

        return $metadata;
    }
}
