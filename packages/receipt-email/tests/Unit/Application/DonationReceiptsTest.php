<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\ReceiptEmail\Application\DonationReceipts;
use Phil\ReceiptEmail\Tests\Support\Donations;
use Phil\ReceiptEmail\Tests\Support\InMemoryDonationReceipts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DonationReceipts::class)]
final class DonationReceiptsTest extends TestCase
{
    public function testEachReceiptIsNumberedInTheSeriesOfDonationReceiptsAndKept(): void
    {
        $store = new InMemoryDonationReceipts();
        $receipts = new DonationReceipts($store, $store);

        $first = $receipts->issue(Donations::cash(), new DateTimeImmutable('2026-11-20'));
        $second = $receipts->issue(Donations::property(), new DateTimeImmutable('2026-11-21'));

        self::assertSame('D-000001', $first->serialNumber);
        self::assertSame('D-000002', $second->serialNumber);
        self::assertSame('2026-11-21', $second->issuedAt->format('Y-m-d'));
        self::assertSame(['D-000001' => $first, 'D-000002' => $second], $store->receipts);
    }
}
