<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnCreditNote;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnReason;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnLine;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for returns and their credit notes.
 */
#[CoversClass(ReturnRequest::class)]
#[CoversClass(ReturnCreditNote::class)]
final class MappingTest extends TestCase
{
    public function testTheTable(): void
    {
        self::assertSame(['phil_commerce_return', ['id'], [
            'lines' => ['lines', 'json', null, false, null],
            'state' => ['state', 'string', 16, false, null],
            'resolution' => ['resolution', 'string', 16, true, null],
            'note' => ['note', 'string', 255, true, null],
            'specialCharges' => ['special_charges', 'json', null, false, null],
            'deliveryRefund' => ['delivery_refund', 'bigint', null, false, null],
            'label' => ['label', 'json', null, true, null],
            'slipNumber' => ['slip_number', 'string', 32, true, null],
            'changedAt' => ['changed_at', 'datetime_immutable', null, false, null],
            'version' => ['version', 'integer', null, false, null],
            'id' => ['id', 'string', 36, false, true],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'restockingFee' => ['restocking_fee', 'integer', null, false, null],
            'requestedAt' => ['requested_at', 'datetime_immutable', null, false, null],
        ]], $this->table(ReturnRequest::class));
        self::assertSame([['columns' => ['order_number']]], array_values($this->metadata(ReturnRequest::class)->table['indexes'] ?? []));
        self::assertTrue($this->metadata(ReturnRequest::class)->isVersioned);

        $return = ReturnRequest::request(str_repeat('o', 64), Currency::of('CAD'), [ReturnLine::of('product:A', 1, ReturnReason::Other, 1, 1)], 0, new DateTimeImmutable());
        self::assertSame($this->metadata(ReturnRequest::class)->fieldMappings['orderNumber']->length, \strlen($return->orderNumber));
        self::assertSame($this->metadata(ReturnRequest::class)->fieldMappings['id']->length, \strlen($return->id));
    }

    public function testTheCreditNoteTable(): void
    {
        self::assertSame(['phil_commerce_return_credit_note', ['number'], [
            'number' => ['number', 'string', 32, false, null],
            'returnId' => ['return_id', 'string', 36, false, true],
            'orderNumber' => ['order_number', 'string', 64, false, null],
            'receiptNumber' => ['receipt_number', 'string', 32, false, null],
            'issuedAt' => ['issued_at', 'datetime_immutable', null, false, null],
            'currencyCode' => ['currency', 'string', 3, false, true],
            'lines' => ['lines', 'json', null, false, null],
            'specialDiscount' => ['special_discount', 'bigint', null, false, null],
            'delivery' => ['delivery', 'bigint', null, false, null],
        ]], $this->table(ReturnCreditNote::class));
        self::assertSame(['phil_commerce_return_credit_note_return' => ['columns' => ['return_id']]], $this->metadata(ReturnCreditNote::class)->table['uniqueConstraints'] ?? []);
        self::assertSame([['columns' => ['order_number']]], array_values($this->metadata(ReturnCreditNote::class)->table['indexes'] ?? []));

        $return = ReturnRequest::request(str_repeat('o', 64), Currency::of('CAD'), [ReturnLine::of('product:A', 1, ReturnReason::Other, 1, 1)], 0, new DateTimeImmutable());
        $note = ReturnCreditNote::issue(str_repeat('n', 32), $return, str_repeat('r', 32), [], new DateTimeImmutable());
        $fields = $this->metadata(ReturnCreditNote::class)->fieldMappings;

        foreach (['number' => $note->number, 'returnId' => $note->returnId, 'orderNumber' => $note->orderNumber, 'receiptNumber' => $note->receiptNumber, 'currencyCode' => $note->currency->code] as $field => $value) {
            self::assertSame($fields[$field]->length, \strlen($value), $field);
        }
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
