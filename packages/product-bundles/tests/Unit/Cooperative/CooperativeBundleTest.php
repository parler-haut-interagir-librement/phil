<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Tests\Unit\Cooperative;

use DateTimeImmutable;
use Phil\Pricing\Rate;
use Phil\ProductBundles\Cooperative\CooperativeBundle;
use Phil\ProductBundles\Cooperative\CooperativeShare;
use Phil\ProductBundles\Exception\CooperativeRefused;
use Phil\ProductBundles\Exception\InvalidBundle;
use Phil\ProductBundles\Tests\Support\Shop;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CooperativeBundle::class)]
#[CoversClass(CooperativeShare::class)]
#[CoversClass(CooperativeRefused::class)]
final class CooperativeBundleTest extends TestCase
{
    public function testADraftWaitsForEveryApproval(): void
    {
        $bundle = $this->draft();

        self::assertSame('ZINES', $bundle->sku);
        self::assertSame('host', $bundle->host);
        self::assertSame(['host', 'ana', '7'], $bundle->participants());
        self::assertSame(['host', 'ana', '7'], $bundle->pending());
        self::assertFalse($bundle->isLocked());
        self::assertFalse($bundle->isOnSale());
    }

    public function testTheFirstApprovalLocksTheSplit(): void
    {
        $first = new DateTimeImmutable('2026-11-24 10:00:00 UTC');
        $bundle = $this->draft()->approve('ana', $first);

        self::assertTrue($bundle->isLocked());
        self::assertSame($first, $bundle->lockedAt);
        self::assertSame(['host', '7'], $bundle->pending());
        self::assertSame($bundle, $bundle->approve('ana', new DateTimeImmutable('2026-11-25 10:00:00 UTC')));

        $later = $bundle->approve('7', new DateTimeImmutable('2026-11-24 11:00:00 UTC'));
        self::assertSame($first, $later->lockedAt);
        $earlier = new DateTimeImmutable('2026-11-24 09:59:59 UTC');
        self::assertSame($earlier, $bundle->approve('host', $earlier)->lockedAt);
        self::assertSame($first, $bundle->approve('host', $first)->lockedAt);

        try {
            $bundle->reweigh(['host' => 1, 'ana' => 1]);
            self::fail('The weights are locked.');
        } catch (CooperativeRefused $cooperativeRefused) {
            self::assertSame('The cooperative bundle "ZINES" refuses the change: the participants and their weights are locked by the first approval.', $cooperativeRefused->getMessage());
        }

        $this->expectExceptionObject(CooperativeRefused::because('ZINES', "the charity's share is locked by the first approval"));
        $bundle->withCharity(Rate::ofPercent('5'));
    }

    public function testADraftIsReweighedAndItsCharityChanged(): void
    {
        $bundle = $this->draft()->reweigh(['host' => 1, 'ana' => 3])->withCharity(Rate::ofPercent('20'));

        self::assertSame(['host' => 1, 'ana' => 3], $bundle->weights);
        self::assertSame(200_000, $bundle->charity->partsPerMillion);
    }

    public function testOnlyAParticipantApproves(): void
    {
        $this->expectExceptionObject(CooperativeRefused::because('ZINES', 'only a participant approves'));
        $this->draft()->approve('stranger', new DateTimeImmutable('2026-11-24 10:00:00 UTC'));
    }

    public function testTheRevenueIsSharedByWeightAfterTheCharity(): void
    {
        $bundle = $this->approved($this->draft(Rate::ofPercent('10')));
        $shares = $bundle->shares(Shop::cad(1000));

        self::assertSame([[null, 100, true], ['host', 450, false], ['ana', 270, false], ['7', 180, false]], array_map(
            static fn (CooperativeShare $share): array => [$share->participant, $share->amount->minorUnits, $share->isCharity()],
            $shares,
        ));
    }

    public function testWithoutACharityOnlyTheParticipantsShare(): void
    {
        $shares = $this->approved($this->draft())->shares(Shop::cad(1001));

        self::assertSame([['host', 501], ['ana', 300], ['7', 200]], array_map(static fn (CooperativeShare $share): array => [$share->participant, $share->amount->minorUnits], $shares));
    }

    public function testACharityBundleGivesEverything(): void
    {
        $shares = $this->approved($this->draft(Rate::of(1_000_000)))->shares(Shop::cad(999));

        self::assertSame([999, 0, 0, 0], array_map(static fn (CooperativeShare $share): int => $share->amount->minorUnits, $shares));
    }

    public function testNoShareBeforeEveryApproval(): void
    {
        $this->expectExceptionObject(CooperativeRefused::because('ZINES', 'shares are reckoned once every participant approved'));
        $this->draft()->approve('host', new DateTimeImmutable('2026-11-24 10:00:00 UTC'))->shares(Shop::cad(100));
    }

    public function testTheSkuOfADraftIsChecked(): void
    {
        $this->expectExceptionObject(InvalidBundle::because('bundle SKU', 'is up to 64 letters, digits, "_", ".", "/" or "-", starting with a letter or a digit'));

        CooperativeBundle::propose('bad sku', 'host', ['host' => 1, 'ana' => 1]);
    }

    public function testTwoApprovalsAtTheSameInstantLockAtTheLatestOne(): void
    {
        $first = new DateTimeImmutable('2026-11-24 10:00:00 UTC');
        $bundle = $this->draft()->approve('ana', $first);

        $same = new DateTimeImmutable('2026-11-24 10:00:00 UTC');

        self::assertSame($same, $bundle->approve('host', $same)->lockedAt);
    }

    /**
     * @param array<string, int> $weights
     */
    #[DataProvider('refusedDrafts')]
    public function testADraftOutOfShapeIsRefused(string $host, array $weights, ?Rate $charity, string $message): void
    {
        $this->expectException(InvalidBundle::class);
        $this->expectExceptionMessageIsOrContains($message);

        CooperativeBundle::propose('ZINES', $host, $weights, $charity);
    }

    /**
     * @return iterable<string, array{string, array<string, int>, Rate|null, string}>
     */
    public static function refusedDrafts(): iterable
    {
        yield 'alone' => ['host', ['host' => 1], null, 'The number of participants is from 2 to 50, not 1.'];
        yield 'crowd' => ['p1', array_fill_keys(array_map(static fn (int $i): string => 'p'.$i, range(1, 51)), 1), null, 'The number of participants is from 2 to 50, not 51.'];
        yield 'host outside' => ['host', ['a' => 1, 'b' => 1], null, 'The host is one of the participants.'];
        yield 'weight zero' => ['host', ['host' => 0, 'b' => 1], null, 'The weight of a participant is from 1 to 10000, not 0.'];
        yield 'weight high' => ['host', ['host' => 10_001, 'b' => 1], null, 'The weight of a participant is from 1 to 10000, not 10001.'];
        yield 'charity high' => ['host', ['host' => 1, 'b' => 1], Rate::of(1_000_001), 'The charity share is from 0 to 1000000, not 1000001.'];
        yield 'charity low' => ['host', ['host' => 1, 'b' => 1], Rate::of(-1), 'The charity share is from 0 to 1000000, not -1.'];
        yield 'empty host' => ['', ['host' => 1, 'b' => 1], null, 'The host is from 1 to 255 printable characters.'];
        yield 'control' => ['host', ['host' => 1, "b\n" => 1], null, 'The participant is from 1 to 255 printable characters.'];
    }

    public function testTheBoundsOfADraftAreAccepted(): void
    {
        $weights = array_fill_keys(array_map(static fn (int $i): string => 'p'.$i, range(1, 50)), 10_000);
        $bundle = CooperativeBundle::propose('ZINES', 'p1', $weights, Rate::of(1_000_000));

        self::assertCount(50, $bundle->participants());
        self::assertSame(0, CooperativeBundle::propose('ZINES', str_repeat('h', 255), [str_repeat('h', 255) => 1, 'b' => 1], Rate::zero())->charity->partsPerMillion);
    }

    public function testABundleIsWrittenAndReadBack(): void
    {
        $draft = $this->draft(Rate::ofPercent('10'));
        self::assertSame($draft->toArray(), CooperativeBundle::fromArray($draft->toArray())->toArray());

        $bundle = $draft->approve('ana', new DateTimeImmutable('@1795600000'))->approve('host', new DateTimeImmutable('@1795500000'));
        $row = $bundle->toArray();

        self::assertSame([
            'sku' => 'ZINES',
            'host' => 'host',
            'charity_ppm' => 100_000,
            'participants' => [
                ['id' => 'host', 'weight' => 5, 'approved_at' => 1795500000],
                ['id' => 'ana', 'weight' => 3, 'approved_at' => 1795600000],
                ['id' => '7', 'weight' => 2, 'approved_at' => null],
            ],
            'locked_at' => 1795500000,
        ], $row);
        self::assertSame($row, CooperativeBundle::fromArray($row)->toArray());
    }

    public function testAStoredBundleReadBackKeepsItsApprovals(): void
    {
        $bundle = $this->draft()->approve('ana', new DateTimeImmutable('@1795500000'))->approve('host', new DateTimeImmutable('@1795600000'));
        $read = CooperativeBundle::fromArray($bundle->toArray());

        self::assertSame($bundle->toArray(), $read->toArray());
        self::assertSame(['7'], $read->pending());
        self::assertTrue($read->isLocked());
        self::assertSame(1795500000, $read->lockedAt?->getTimestamp());
    }

    /**
     * @param array<array-key, mixed> $change
     */
    #[DataProvider('refusedRows')]
    public function testAStoredRowOutOfShapeIsRefused(array $change, string $message): void
    {
        $this->expectException(InvalidBundle::class);
        $this->expectExceptionMessageIsOrContains($message);

        CooperativeBundle::fromArray($change + $this->draft()->toArray());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refusedRows(): iterable
    {
        yield 'twice' => [['participants' => [['id' => 'host', 'weight' => 1, 'approved_at' => null], ['id' => 'host', 'weight' => 1, 'approved_at' => null]]], 'The stored participant "host" appears once.'];
        yield 'entry' => [['participants' => ['host']], 'The stored participant is an array.'];
        yield 'lock without approval' => [['locked_at' => 1795500000], 'The stored lock is the instant of the first approval.'];
        yield 'approval without lock' => [['participants' => [['id' => 'host', 'weight' => 1, 'approved_at' => 1795500000], ['id' => 'b', 'weight' => 1, 'approved_at' => null]]], 'The stored lock is the instant of the first approval.'];
        yield 'instant' => [['participants' => [['id' => 'host', 'weight' => 1, 'approved_at' => -1], ['id' => 'b', 'weight' => 1, 'approved_at' => null]], 'locked_at' => -1], 'The stored instant is from 0 to 253402300799, not -1.'];
        yield 'charity' => [['charity_ppm' => '0'], 'The stored field "charity_ppm" is an integer.'];
    }

    private function draft(?Rate $charity = null): CooperativeBundle
    {
        return CooperativeBundle::propose('ZINES', 'host', ['host' => 5, 'ana' => 3, '7' => 2], $charity);
    }

    private function approved(CooperativeBundle $bundle): CooperativeBundle
    {
        foreach ($bundle->participants() as $participant) {
            $bundle = $bundle->approve($participant, new DateTimeImmutable('2026-11-24 10:00:00 UTC'));
        }

        self::assertTrue($bundle->isOnSale());

        return $bundle;
    }
}
