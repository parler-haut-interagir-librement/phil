<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Domain;

use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\ValueObject\Attainment;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(VerificationCode::class)]
#[CoversClass(Attainment::class)]
#[CoversClass(PassCriteria::class)]
#[CoversClass(InvalidValue::class)]
final class ValueObjectTest extends TestCase
{
    public function testAGeneratedCodeIsTwelveCharactersOfTheWholeAlphabet(): void
    {
        $seen = [];

        for ($i = 0; $i < 300; ++$i) {
            $code = VerificationCode::generate()->value;
            self::assertMatchesRegularExpression('/^'.VerificationCode::PATTERN.'$/D', $code);
            $seen += array_flip(str_split($code));
        }

        self::assertCount(32, $seen);
        self::assertNotSame(VerificationCode::generate()->value, VerificationCode::generate()->value);
    }

    public function testACodeReadBackForgivesCaseSeparatorsAndLettersReadAsDigits(): void
    {
        self::assertSame('7KQ2M9XA04TZ', VerificationCode::fromString('7kq2-m9xa-o4tz')->value);
        self::assertSame('11000000ABCD', VerificationCode::fromString('IL OO 0000 abcd')->value);
        self::assertSame('7KQ2-M9XA-04TZ', VerificationCode::fromString('7KQ2M9XA04TZ')->formatted());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCodes(): iterable
    {
        yield 'too short' => ['7KQ2M9XA04T'];
        yield 'too long' => ['7KQ2M9XA04TZ0'];
        yield 'longer in front' => ['07KQ2M9XA04TZ'];
        yield 'a line break after' => ["7KQ2M9XA04TZ\n"];
        yield 'a letter Crockford leaves out' => ['7KQ2M9XA04TU'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidCodes')]
    public function testACodeOfAnotherShapeIsRefused(string $code): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('is not a verification code');

        VerificationCode::fromString($code);
    }

    public function testAnAttainmentHoldsAScoreFromZeroToAHundredAndMinutesNotNegative(): void
    {
        self::assertSame(0, new Attainment(0)->score);
        self::assertSame(100, new Attainment(100, 0)->score);
        self::assertNull(new Attainment()->score);
        self::assertSame(0, new Attainment()->minutes);
        self::assertSame(['m1'], new Attainment(completedModules: ['m1'])->completedModules);

        foreach ([[-1, 0], [101, 0], [null, -1]] as [$score, $minutes]) {
            try {
                new Attainment($score, $minutes);
                self::fail('The attainment is refused.');
            } catch (InvalidValue $invalidValue) {
                self::assertNotSame('', $invalidValue->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{?int, ?int, list<string>}>
     */
    public static function invalidCriteria(): iterable
    {
        yield 'a minimum score of zero' => [0, null, []];
        yield 'a minimum score above a hundred' => [101, null, []];
        yield 'no minute' => [null, 0, []];
        yield 'too many modules' => [null, null, array_map(static fn (int $i): string => 'm'.$i, range(0, 100))];
        yield 'an empty module' => [null, null, ['']];
        yield 'a blank module' => [null, null, ['  ']];
        yield 'a module code too long' => [null, null, [str_repeat('é', 65)]];
        yield 'a module twice' => [null, null, ['m1', 'm2', 'm1']];
    }

    /**
     * @param list<string> $modules
     */
    #[DataProvider('invalidCriteria')]
    public function testCriteriaThatDoNotHoldTogetherAreRefused(?int $score, ?int $minutes, array $modules): void
    {
        $this->expectException(InvalidValue::class);

        new PassCriteria($score, $minutes, $modules);
    }

    public function testCriteriaAtTheirBoundsAreAccepted(): void
    {
        $modules = array_map(static fn (int $i): string => 'm'.$i, range(1, 100));

        self::assertSame(1, new PassCriteria(1, 1)->minimumScore);
        self::assertSame(100, new PassCriteria(100)->minimumScore);
        self::assertSame(1, new PassCriteria(minimumMinutes: 1)->minimumMinutes);
        self::assertSame($modules, new PassCriteria(requiredModules: $modules)->requiredModules);
        self::assertSame([str_repeat('é', 64)], new PassCriteria(requiredModules: [str_repeat('é', 64)])->requiredModules);
    }

    public function testWhatAnAttainmentLacksIsListedInOrder(): void
    {
        $criteria = new PassCriteria(70, 120, ['intro', 'cpr', 'burns']);

        self::assertSame(['score', 'minutes', 'module:cpr', 'module:burns'], $criteria->unmet(new Attainment(69, 119, ['intro'])));
        self::assertSame(['score'], $criteria->unmet(new Attainment(null, 120, ['burns', 'cpr', 'intro'])));
        self::assertSame([], $criteria->unmet(new Attainment(70, 120, ['intro', 'cpr', 'burns', 'extra'])));
        self::assertTrue($criteria->isMetBy(new Attainment(100, 500, ['intro', 'cpr', 'burns'])));
        self::assertFalse($criteria->isMetBy(new Attainment(100, 500, ['intro', 'cpr'])));
        self::assertSame(['score'], new PassCriteria(1)->unmet(new Attainment()));
        self::assertSame([], new PassCriteria(1)->unmet(new Attainment(1)));
        self::assertTrue(new PassCriteria()->isMetBy(new Attainment()));
    }
}
