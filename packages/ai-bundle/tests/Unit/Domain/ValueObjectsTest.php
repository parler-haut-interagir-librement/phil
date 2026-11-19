<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit\Domain;

use Phil\AiBundle\Domain\Exception\AiException;
use Phil\AiBundle\Domain\Exception\InvalidAiValue;
use Phil\AiBundle\Domain\ValueObject\Cost;
use Phil\AiBundle\Domain\ValueObject\Price;
use Phil\AiBundle\Domain\ValueObject\Subject;
use Phil\AiBundle\Domain\ValueObject\TaskKind;
use Phil\AiBundle\Domain\ValueObject\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaskKind::class)]
#[CoversClass(Subject::class)]
#[CoversClass(Tokens::class)]
#[CoversClass(Cost::class)]
#[CoversClass(Price::class)]
#[CoversClass(InvalidAiValue::class)]
final class ValueObjectsTest extends TestCase
{
    public function testATaskKindIsALowercaseName(): void
    {
        self::assertSame('draft.rewrite', TaskKind::fromString('draft.rewrite')->value);
        self::assertSame('a', TaskKind::fromString('a')->value);
        self::assertSame('a'.str_repeat('-_9', 21), TaskKind::fromString('a'.str_repeat('-_9', 21))->value);

        foreach (['', 'Draft', '9draft', '.draft', 'draft rewrite', "draft\n", 'a'.str_repeat('b', 64)] as $value) {
            try {
                TaskKind::fromString($value);
                self::fail($value);
            } catch (InvalidAiValue $invalidAiValue) {
                self::assertInstanceOf(AiException::class, $invalidAiValue);
                self::assertSame(\sprintf('"%s" is not a task kind: up to 64 lowercase letters, digits, "_", "." or "-", starting with a letter.', $value), $invalidAiValue->getMessage());
            }
        }
    }

    public function testASubjectIsOneTo255Bytes(): void
    {
        self::assertSame('p', Subject::fromString('p')->value);
        self::assertSame(str_repeat('x', 255), Subject::fromString(str_repeat('x', 255))->value);

        foreach (['', str_repeat('x', 256)] as $value) {
            try {
                Subject::fromString($value);
                self::fail($value);
            } catch (InvalidAiValue $invalidAiValue) {
                self::assertSame('A subject is named by 1 to 255 bytes.', $invalidAiValue->getMessage());
            }
        }
    }

    public function testTokensAreCountedInAndOut(): void
    {
        $tokens = new Tokens(12, 5);

        self::assertSame([12, 5, 17], [$tokens->input, $tokens->output, $tokens->total()]);
        self::assertSame([0, 0, 0], [Tokens::none()->input, Tokens::none()->output, Tokens::none()->total()]);

        foreach ([[-1, 0], [0, -1]] as [$input, $output]) {
            try {
                new Tokens($input, $output);
                self::fail(\sprintf('%d/%d', $input, $output));
            } catch (InvalidAiValue $invalidAiValue) {
                self::assertSame(\sprintf('A token count is zero or more; got %d in and %d out.', $input, $output), $invalidAiValue->getMessage());
            }
        }
    }

    public function testACostIsMicrosOfACurrency(): void
    {
        $cost = new Cost(1_500_000, 'CAD');

        self::assertSame([1_500_000, 'CAD'], [$cost->micros, $cost->currency]);
        self::assertSame(0, new Cost(0, 'USD')->micros);

        try {
            new Cost(-1, 'USD');
            self::fail('A negative cost was let through.');
        } catch (InvalidAiValue $invalidAiValue) {
            self::assertSame('A cost is zero or more; got -1 micros.', $invalidAiValue->getMessage());
        }

        foreach (['usd', 'US', 'USDT', "USD\n", ''] as $currency) {
            try {
                new Cost(1, $currency);
                self::fail($currency);
            } catch (InvalidAiValue $invalidAiValue) {
                self::assertSame(\sprintf('"%s" is not a currency code: three uppercase letters.', $currency), $invalidAiValue->getMessage());
            }
        }
    }

    public function testAPriceCostsTokensRoundedUpToTheMicro(): void
    {
        $price = new Price(3_000_000, 15_000_000, 'USD');

        self::assertSame([3_000_000, 15_000_000, 'USD'], [$price->inputPerMillion, $price->outputPerMillion, $price->currency]);
        self::assertSame(36 + 75, $price->costOf(new Tokens(12, 5))->micros);
        self::assertSame('USD', $price->costOf(new Tokens(12, 5))->currency);
        self::assertSame(0, $price->costOf(Tokens::none())->micros);
        self::assertSame(1, new Price(1, 0, 'USD')->costOf(new Tokens(1, 0))->micros);
        self::assertSame(40_001, new Price(1, 0, 'USD', 40_000)->costOf(new Tokens(1, 0))->micros);
        self::assertSame(40_000, new Price(0, 0, 'USD', 40_000)->costOf(Tokens::none())->micros);
        self::assertSame(0, new Price(0, 0, 'USD', 0)->perCall);
        self::assertSame(1, new Price(1_000_000, 0, 'USD')->costOf(new Tokens(1, 0))->micros);
        self::assertSame(2, new Price(1_000_001, 0, 'USD')->costOf(new Tokens(1, 0))->micros);
        self::assertSame(1, new Price(0, 999_999, 'USD')->costOf(new Tokens(0, 1))->micros);
        self::assertSame(0, new Price(0, 0, 'USD')->costOf(new Tokens(1_000, 1_000))->micros);

        foreach ([[-1, 0, 0], [0, -1, 0], [0, 0, -1]] as [$input, $output, $perCall]) {
            try {
                new Price($input, $output, 'USD', $perCall);
                self::fail(\sprintf('%d/%d', $input, $output));
            } catch (InvalidAiValue $invalidAiValue) {
                self::assertSame('A price is zero or more.', $invalidAiValue->getMessage());
            }
        }

        $this->expectExceptionObject(InvalidAiValue::because('"eur" is not a currency code: three uppercase letters.'));
        new Price(1, 1, 'eur');
    }
}
