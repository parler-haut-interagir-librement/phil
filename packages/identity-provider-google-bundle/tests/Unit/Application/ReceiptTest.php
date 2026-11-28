<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Tests\Unit\Application;

use Phil\IdentityProviderGoogleBundle\Application\Receipt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Receipt::class)]
final class ReceiptTest extends TestCase
{
    public function testAReceiptCountsNothingUnlessToldSo(): void
    {
        $receipt = new Receipt(true);

        self::assertTrue($receipt->duplicate);
        self::assertSame(0, $receipt->events);
        self::assertSame(0, $receipt->sessionsClosed);
    }
}
