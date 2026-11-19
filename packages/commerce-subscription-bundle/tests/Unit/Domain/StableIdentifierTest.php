<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Domain;

use Phil\CommerceSubscriptionBundle\Domain\ValueObject\StableIdentifier;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(StableIdentifier::class)]
final class StableIdentifierTest extends TestCase
{
    public function testAStableIdentifierIsKept(): void
    {
        self::assertSame('0193f1d2-7c1e-7a4b-9f00-1a2b3c4d5e6f', StableIdentifier::of('0193f1d2-7c1e-7a4b-9f00-1a2b3c4d5e6f', 'member')->value);
        self::assertSame(str_repeat('a', 255), StableIdentifier::of(str_repeat('a', 255), 'member')->value);
        self::assertSame('!?A~', StableIdentifier::of('!?A~', 'member')->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'empty' => [''];
        yield 'an email address' => ['ann@example.com'];
        yield 'a space' => ['ann smith'];
        yield 'too long' => [str_repeat('a', 256)];
        yield 'a line break after' => ["ann\n"];
    }

    #[DataProvider('refused')]
    public function testWhatCouldChangeIsRefused(string $value): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('is not the stable identifier of a member: 1 to 255 visible ASCII characters without "@", never an email address or a username.');

        StableIdentifier::of($value, 'member');
    }
}
