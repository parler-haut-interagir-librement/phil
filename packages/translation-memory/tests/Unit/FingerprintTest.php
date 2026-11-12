<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit;

use Phil\TranslationMemory\Exception\InvalidSegment;
use Phil\TranslationMemory\Fingerprint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Fingerprint::class)]
#[CoversClass(InvalidSegment::class)]
final class FingerprintTest extends TestCase
{
    public function testIsTheSha256OfTheNormalizedText(): void
    {
        self::assertSame(hash('sha256', 'a b'), Fingerprint::of(" a \n b ")->value);
        self::assertTrue(Fingerprint::of('a  b')->equals(Fingerprint::of('a b')));
        self::assertFalse(Fingerprint::of('a b')->equals(Fingerprint::of('a <b>b</b>')));
    }

    public function testIsReadBackFromStorage(): void
    {
        $value = hash('sha256', 'x');

        self::assertTrue(Fingerprint::fromString($value)->equals(Fingerprint::of('x')));
    }

    public function testRefusesAValueThatIsNotOne(): void
    {
        $this->expectException(InvalidSegment::class);
        $this->expectExceptionMessageIsOrContains('"'.strtoupper(hash('sha256', 'x')).'" is not a fingerprint: 64 lower-case hexadecimal digits are expected.');

        Fingerprint::fromString(strtoupper(hash('sha256', 'x')));
    }

    public function testRefusesAValueWithMore(): void
    {
        $this->expectException(InvalidSegment::class);

        Fingerprint::fromString(hash('sha256', 'x')."\n");
    }

    public function testRefusesAValueWithAPrefix(): void
    {
        $this->expectException(InvalidSegment::class);

        Fingerprint::fromString('0'.hash('sha256', 'x'));
    }
}
