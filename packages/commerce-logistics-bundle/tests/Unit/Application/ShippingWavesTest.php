<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceLogisticsBundle\Application\ShippingWaves;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\ShippingWave;
use Phil\CommerceLogisticsBundle\Domain\Model\WaveAssignment;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShippingWaves::class)]
#[CoversClass(ShippingWave::class)]
#[CoversClass(WaveAssignment::class)]
final class ShippingWavesTest extends TestCase
{
    private InMemoryLogistics $repository;

    private ShippingWaves $waves;

    protected function setUp(): void
    {
        $this->repository = new InMemoryLogistics();
        $this->waves = new ShippingWaves($this->repository);
        $this->waves->plan('january', 'January run', new DateTimeImmutable('2027-01-15 14:00'));
        $this->waves->plan('december', 'December run', new DateTimeImmutable('2026-12-10'));
    }

    public function testOrdersArePlannedInAWaveAndShowItsEstimatedDay(): void
    {
        $this->waves->assign('2026-0002', 'january');
        $this->waves->assign('2026-0001', 'january');

        self::assertSame('2027-01-15 00:00', $this->waves->estimatedOn('2026-0001')?->format('Y-m-d H:i'));
        self::assertSame(['december', 'january'], array_map(static fn (ShippingWave $wave): string => $wave->code, $this->waves->planned()));

        $this->waves->reschedule('january', new DateTimeImmutable('2027-01-29'));
        self::assertSame('2027-01-29', $this->waves->estimatedOn('2026-0002')?->format('Y-m-d'));
        self::assertSame(['2026-0001', '2026-0002'], $this->waves->ordersOf('january'));
        self::assertNull($this->waves->estimatedOn('2026-0099'));
    }

    public function testAnOrderMovesFromOneWaveToAnotherAndOut(): void
    {
        $this->waves->assign('2026-0001', 'january');
        $this->waves->assign('2026-0001', 'december');

        self::assertSame('december', $this->waves->waveOf('2026-0001')?->code);
        self::assertSame([], $this->waves->ordersOf('january'));

        $this->waves->withdraw('2026-0001');
        $this->waves->withdraw('2026-0001');
        self::assertNull($this->waves->waveOf('2026-0001'));
    }

    public function testAReleasedWaveHandsOverItsOrdersAndHoldsThem(): void
    {
        $this->waves->assign('2026-0001', 'december');
        $at = new DateTimeImmutable('2026-12-10T08:00:00Z');

        self::assertSame(['2026-0001'], $this->waves->release('december', $at));
        self::assertSame($at, $this->repository->findWave('december')?->releasedAt());
        self::assertSame(['january'], array_map(static fn (ShippingWave $wave): string => $wave->code, $this->waves->planned()));

        foreach ([
            fn () => $this->waves->assign('2026-0002', 'december'),
            fn () => $this->waves->assign('2026-0001', 'january'),
            fn () => $this->waves->withdraw('2026-0001'),
            fn () => $this->waves->reschedule('december', $at),
            fn (): array => $this->waves->release('december', $at),
        ] as $refused) {
            try {
                $refused();
                self::fail('A released wave changed.');
            } catch (StockRefused $exception) {
                self::assertSame('The wave december is released: its orders are shipping.', $exception->getMessage());
            }
        }
    }

    public function testAWaveIsPlannedOnceAndKnown(): void
    {
        try {
            $this->waves->plan('january', 'Again', new DateTimeImmutable('2027-01-01'));
            self::fail('A wave was planned twice.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('The wave january already exists.', $invalidValue->getMessage());
        }

        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('No shipping wave is known as "march".');

        $this->waves->assign('2026-0001', 'march');
    }

    public function testAnOrderPlannedInAWaveThatNoLongerExistsLeavesIt(): void
    {
        $this->repository->addAssignment(WaveAssignment::of('2026-0003', ShippingWave::plan('ghost', 'Ghost run', new DateTimeImmutable('2027-02-01'))));

        $this->waves->withdraw('2026-0003');

        self::assertNull($this->repository->assignmentOf('2026-0003'));
    }

    public function testAWaveKeepsTheDayItWasRescheduledToWithoutAnyTime(): void
    {
        $this->waves->reschedule('january', new DateTimeImmutable('2027-01-29 15:45'));

        self::assertSame('2027-01-29 00:00', $this->repository->findWave('january')?->estimatedOn()->format('Y-m-d H:i'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function wavesRefused(): iterable
    {
        yield 'a code with a space' => ['a b', 'is not a wave code'];
        yield 'a code with a trailing line feed' => ["january\n", 'is not a wave code'];
        yield 'a code of 65 characters' => [str_repeat('a', 65), 'is not a wave code'];
    }

    #[DataProvider('wavesRefused')]
    public function testACodeOfAnotherShapeIsRefused(string $code, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        ShippingWave::plan($code, 'A run', new DateTimeImmutable('2027-01-15'));
    }

    public function testANameOfTwoHundredFiftyFiveMultibyteCharactersIsKept(): void
    {
        self::assertSame(255, mb_strlen(ShippingWave::plan('march', str_repeat('é', 255), new DateTimeImmutable('2027-03-01'))->name));
    }

    public function testAWaveHasACodeAndAName(): void
    {
        try {
            ShippingWave::plan('January', 'January run', new DateTimeImmutable('2027-01-15'));
            self::fail('An uppercase code was taken.');
        } catch (InvalidValue $invalidValue) {
            self::assertStringContainsString('is not a wave code', $invalidValue->getMessage());
        }

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The wave january has a name of 1 to 255 characters.');

        ShippingWave::plan('january', ' ', new DateTimeImmutable('2027-01-15'));
    }
}
