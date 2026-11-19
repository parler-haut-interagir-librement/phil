<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\CommerceReturnsBundle\Infrastructure\StoredValue\StoredValueStoreCredit;
use Phil\CommerceReturnsBundle\Tests\Unit\Support\InMemoryInstruments;
use Phil\CommerceStoredValueBundle\Application\StoreCredits;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StoredValueStoreCredit::class)]
final class StoredValueStoreCreditTest extends TestCase
{
    public function testTheCreditGoesToTheCustomersStoredValue(): void
    {
        $instruments = new InMemoryInstruments();
        $credits = new StoreCredits($instruments);

        new StoredValueStoreCredit($credits)->grant('ada', Money::of(1_500, Currency::of('CAD')), new DateTimeImmutable(), 'Return r-1', 'A-1');

        self::assertSame(1_500, $credits->balance('ada', Currency::of('CAD'))->minorUnits);
        self::assertSame(['Return r-1', 'A-1'], [$instruments->movements[0]->note, $instruments->movements[0]->orderNumber]);
    }
}
