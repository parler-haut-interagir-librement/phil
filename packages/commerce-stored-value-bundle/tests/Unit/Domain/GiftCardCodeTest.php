<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Tests\Unit\Domain;

use Phil\CommerceStoredValueBundle\Domain\ValueObject\GiftCardCode;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

#[CoversClass(GiftCardCode::class)]
final class GiftCardCodeTest extends TestCase
{
    public function testACodeIsSixteenCharactersInFourGroups(): void
    {
        $code = GiftCardCode::fromString('7KQ2M9XA3TFDW8HC');

        self::assertSame('7KQ2-M9XA-3TFD-W8HC', $code->toString());
        self::assertSame('7KQ2-M9XA-3TFD-W8HC', (string) $code);
        self::assertSame('W8HC', $code->lastFour());
        self::assertSame(hash('sha256', '7KQ2M9XA3TFDW8HC'), $code->hash());
        self::assertSame(['lastFour' => 'W8HC'], $code->__debugInfo());
    }

    public function testWhatAPersonTypesIsForgiven(): void
    {
        self::assertSame('7KQ2-M9XA-3TF0-1110', GiftCardCode::fromString(' 7kq2 m9xa-3tfo-ilio ')->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badCodes(): iterable
    {
        yield 'too short' => ['7KQ2-M9XA-3TFD-W8H'];
        yield 'too long' => ['7KQ2-M9XA-3TFD-W8HCX'];
        yield 'a U' => ['7KQ2-M9XA-3TFD-W8HU'];
        yield 'a sign' => ['7KQ2-M9XA-3TFD-W8H!'];
        yield 'a trailing newline' => ["7KQ2M9XA3TFDW8HC\n"];
    }

    #[DataProvider('badCodes')]
    public function testOtherCodesAreRefused(string $code): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A gift card code is sixteen digits and letters.');

        GiftCardCode::fromString($code);
    }

    public function testAGeneratedCodeIsRandomAndReadable(): void
    {
        $code = GiftCardCode::generate(new Randomizer(new Mt19937(42)));

        self::assertSame($code->toString(), GiftCardCode::generate(new Randomizer(new Mt19937(42)))->toString());
        self::assertMatchesRegularExpression('/^([0-9A-HJKMNP-TV-Z]{4}-){3}[0-9A-HJKMNP-TV-Z]{4}$/D', $code->toString());
        self::assertNotSame(GiftCardCode::generate()->toString(), GiftCardCode::generate()->toString());

        $letters = '';

        for ($i = 0; $i < 200; ++$i) {
            $letters .= str_replace('-', '', GiftCardCode::generate()->toString());
        }

        self::assertCount(32, array_unique(str_split($letters)));
    }
}
