<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\ValueObject;

use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SiteId::class)]
final class SiteIdTest extends TestCase
{
    public function testAReferenceIsKeptAsGiven(): void
    {
        $site = SiteId::fromString('my-site');

        self::assertSame('my-site', (string) $site);
        self::assertTrue($site->equals(SiteId::fromString('my-site')));
        self::assertFalse($site->equals(SiteId::fromString('My-site')));
        self::assertSame(str_repeat('a', 255), (string) SiteId::fromString(str_repeat('a', 255)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notAReference(): iterable
    {
        yield 'empty' => [''];
        yield 'padded' => [' site'];
        yield 'control character' => ["si\nte"];
        yield 'too long' => [str_repeat('a', 256)];
    }

    #[DataProvider('notAReference')]
    public function testWhatCannotBeAStableKeyIsRefused(string $value): void
    {
        $this->expectException(InvalidValue::class);

        SiteId::fromString($value);
    }
}
