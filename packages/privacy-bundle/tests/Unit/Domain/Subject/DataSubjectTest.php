<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Subject;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataSubject::class)]
final class DataSubjectTest extends TestCase
{
    public function testItKeepsTheIdentifierAsGiven(): void
    {
        $subject = DataSubject::fromString('0b9e6b8a-1c2d-4e5f-8a9b-0c1d2e3f4a5b');

        self::assertSame('0b9e6b8a-1c2d-4e5f-8a9b-0c1d2e3f4a5b', $subject->value);
        self::assertSame('0b9e6b8a-1c2d-4e5f-8a9b-0c1d2e3f4a5b', (string) $subject);
    }

    public function testTwoSubjectsWithTheSameIdentifierAreEqual(): void
    {
        self::assertTrue(DataSubject::fromString('abc')->equals(DataSubject::fromString('abc')));
        self::assertFalse(DataSubject::fromString('abc')->equals(DataSubject::fromString('ABC')));
    }

    public function testTheMaximumLengthIsAccepted(): void
    {
        self::assertSame(255, \strlen(DataSubject::fromString(str_repeat('a', 255))->value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusable(): iterable
    {
        yield 'empty' => [''];
        yield 'trailing newline' => ["abc\n"];
        yield 'inner space' => ['a b'];
        yield 'control character' => ["a\x00b"];
        yield 'too long' => [str_repeat('a', 256)];
    }

    #[DataProvider('unusable')]
    public function testAnIdentifierNoModuleCanIndexOnIsRefused(string $value): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        DataSubject::fromString($value);
    }
}
