<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommerceLogisticsBundle\Domain\Model\Convention;
use Phil\CommerceLogisticsBundle\Domain\Model\ConventionQuantity;
use Phil\CommerceLogisticsBundle\Domain\Model\PrintJob;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Convention::class)]
#[CoversClass(ConventionQuantity::class)]
#[CoversClass(PrintJob::class)]
final class ConventionTest extends TestCase
{
    public function testAConventionKeepsItsDaysWithoutTheirTime(): void
    {
        $convention = Convention::of('fall-con', 'Fall Con', new DateTimeImmutable('2026-12-20 09:00'), new DateTimeImmutable('2026-12-20 18:00'));

        self::assertSame(['2026-12-20 00:00', '2026-12-20 00:00'], [$convention->startsOn()->format('Y-m-d H:i'), $convention->endsOn()->format('Y-m-d H:i')]);
        self::assertTrue($convention->meets(new DateTimeImmutable('2026-12-20 23:59'), new DateTimeImmutable('2026-12-30')));
        self::assertTrue($convention->meets(new DateTimeImmutable('2026-12-01'), new DateTimeImmutable('2026-12-20 00:01')));
        self::assertFalse($convention->meets(new DateTimeImmutable('2026-12-21'), new DateTimeImmutable('2026-12-30')));
        self::assertFalse($convention->meets(new DateTimeImmutable('2026-12-01'), new DateTimeImmutable('2026-12-19 23:59')));
    }

    /**
     * @return iterable<string, array{string, string, string, string, string}>
     */
    public static function invalidConventions(): iterable
    {
        yield 'an uppercase code' => ['Fall', 'Fall Con', '2026-12-20', '2026-12-21', 'is not a convention code'];
        yield 'a blank name' => ['fall', ' ', '2026-12-20', '2026-12-21', 'has a name of 1 to 255 characters'];
        yield 'a name too long' => ['fall', str_repeat('n', 256), '2026-12-20', '2026-12-21', 'has a name of 1 to 255 characters'];
        yield 'an end before its start' => ['fall', 'Fall Con', '2026-12-20', '2026-12-19', 'ends on its first day or later'];
    }

    #[DataProvider('invalidConventions')]
    public function testAnInvalidConventionIsRefused(string $code, string $name, string $startsOn, string $endsOn, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        Convention::of($code, $name, new DateTimeImmutable($startsOn), new DateTimeImmutable($endsOn));
    }

    public function testAQuantityIsZeroOrMoreOfAValidSku(): void
    {
        $convention = Convention::of('fall', 'Fall Con', new DateTimeImmutable('2026-12-20'), new DateTimeImmutable('2026-12-21'));
        $quantity = ConventionQuantity::of($convention, 'SHIRT-M', 0);
        $quantity->change(4);

        self::assertSame(['fall', 'SHIRT-M', 4], [$quantity->convention, $quantity->sku, $quantity->quantity()]);

        try {
            $quantity->change(-1);
            self::fail('A negative quantity was taken.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('A convention is prepared zero units or more; -1 is refused.', $invalidValue->getMessage());
        }

        $this->expectException(InvalidValue::class);
        ConventionQuantity::of($convention, 'SHIRT M', 1);
    }

    public function testAPrintJobKeepsWhatWentToPrint(): void
    {
        $at = new DateTimeImmutable('2026-12-01T10:00:00Z');
        $job = PrintJob::of('2026-0001', 'SHIRT-M', 2, $at);

        self::assertSame(['2026-0001', 'SHIRT-M', 2, $at, 36], [$job->orderNumber, $job->sku, $job->quantity, $job->sentAt, \strlen($job->id)]);
    }
}
