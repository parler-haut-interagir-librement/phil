<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Messenger;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Infrastructure\Messenger\TenantStamp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantStamp::class)]
final class TenantStampTest extends TestCase
{
    public function testTheStampCarriesTheReferenceOnly(): void
    {
        self::assertSame('retail', new TenantStamp('retail')->tenant);
    }

    public function testAReferenceThatCannotBeATenantIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        new TenantStamp(str_repeat('a', 256));
    }
}
