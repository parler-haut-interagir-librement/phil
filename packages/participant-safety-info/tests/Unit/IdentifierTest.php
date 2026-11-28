<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Tests\Unit;

use Phil\ParticipantSafetyInfo\Exception\InvalidSafetyInfo;
use Phil\ParticipantSafetyInfo\Identifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Identifier::class)]
#[CoversClass(InvalidSafetyInfo::class)]
final class IdentifierTest extends TestCase
{
    public function testAnIdentifierOfOneTo255BytesPasses(): void
    {
        self::assertSame('a', Identifier::check('a', 'An event'));
        self::assertSame(str_repeat('a', 255), Identifier::check(str_repeat('a', 255), 'An event'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'empty' => [''];
        yield '256 bytes' => [str_repeat('a', 256)];
        yield 'a newline' => ["a\nb"];
        yield 'a null byte' => ["a\0"];
        yield 'a unit separator' => ["a\x1Fb"];
        yield 'a delete' => ["a\x7F"];
    }

    #[DataProvider('refused')]
    public function testAnIdentifierOutOfBoundsIsRefused(string $value): void
    {
        $this->expectException(InvalidSafetyInfo::class);
        $this->expectExceptionMessageIsOrContains('A reader is named by 1 to 255 bytes, without a control character.');

        Identifier::check($value, 'A reader');
    }
}
