<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Tests\Unit;

use Phil\AccessCodes\AccessCodes;
use Phil\AccessCodes\Batch;
use Phil\AccessCodes\BatchCounts;
use Phil\AccessCodes\CodeAlphabet;
use Phil\AccessCodes\CodeFormat;
use Phil\AccessCodes\CodeHasher;
use Phil\AccessCodes\Exception\InvalidConfiguration;
use Phil\AccessCodes\Exception\UnknownBatch;
use Phil\AccessCodes\InMemoryCodeStore;
use Phil\AccessCodes\Redemption;
use Phil\AccessCodes\RedemptionOutcome;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(AccessCodes::class)]
#[CoversClass(Redemption::class)]
#[CoversClass(RedemptionOutcome::class)]
#[CoversClass(UnknownBatch::class)]
final class AccessCodesTest extends TestCase
{
    private const string KEY = 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk';

    private InMemoryCodeStore $store;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->store = new InMemoryCodeStore();
        $this->clock = new MockClock('2026-11-13 10:00:00', 'UTC');
    }

    public function testTheRandomizerGivenDecidesTheCodes(): void
    {
        $first = $this->codes()->create('Cards', 3)->takeCodes(display: false);
        $this->store = new InMemoryCodeStore();
        $second = $this->codes()->create('Cards', 3)->takeCodes(display: false);

        self::assertSame($first, $second);
    }

    public function testACodeOfDigitsOnlyStaysAString(): void
    {
        // The seed gives, over sixteen symbols, a code of ten digits that an array key would turn into an integer.
        $codes = new AccessCodes($this->store, new CodeHasher(self::KEY), $this->limiter(100), $this->clock, new CodeFormat(new CodeAlphabet('0123456789ABCDEF'), 10, false), new Randomizer(new Xoshiro256StarStar(15)));

        self::assertSame(['2912424665'], $codes->create('Digits', 1)->takeCodes(display: false));
    }

    public function testABatchIsCreatedWithDistinctCodesStoredHashed(): void
    {
        $codes = $this->codes();
        $created = $codes->create('  Convention cards ', 50, $this->clock->now()->modify('+30 days'));

        self::assertSame('Convention cards', $created->batch->name);
        self::assertSame(50, $created->batch->size);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $created->batch->id);
        self::assertSame('2026-11-13 10:00:00', $created->batch->createdAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-12-13 10:00:00', $created->batch->expiresAt?->format('Y-m-d H:i:s'));

        $plain = $created->takeCodes(display: false);
        self::assertCount(50, array_unique($plain));
        self::assertSame(50, $this->store->counts($created->batch->id)->total);
        self::assertSame($created->batch->id, $this->store->find(new CodeHasher(self::KEY)->candidates($plain[0]))?->batchId);
        self::assertSame($created->batch->id, $codes->batch($created->batch->id)->id);
    }

    public function testACodeIsRedeemedOnceAsTyped(): void
    {
        $codes = $this->codes();
        $created = $codes->create('Cards', 2);
        [$first] = $created->takeCodes();
        $this->clock->sleep(60);

        $redemption = $codes->redeem(strtolower($first), 'member-7', '203.0.113.9');

        self::assertSame(RedemptionOutcome::Redeemed, $redemption->outcome);
        self::assertTrue($redemption->isRedeemed());
        self::assertSame($created->batch->id, $redemption->batchId);
        self::assertSame('2026-11-13 10:01:00', $redemption->redeemedAt?->format('Y-m-d H:i:s'));
        self::assertNull($redemption->retryAfter);

        $again = $codes->redeem($first, 'member-8', '203.0.113.9');

        self::assertSame(RedemptionOutcome::AlreadyRedeemed, $again->outcome);
        self::assertFalse($again->isRedeemed());
        self::assertSame($created->batch->id, $again->batchId);
        self::assertSame('2026-11-13 10:01:00', $again->redeemedAt?->format('Y-m-d H:i:s'));

        $counts = $codes->counts($created->batch->id);
        self::assertSame([2, 1, 0, 1], [$counts->total, $counts->redeemed, $counts->revoked, $counts->unused()]);
    }

    public function testAnUnknownOrMistypedCodeIsUnknown(): void
    {
        $codes = $this->codes();
        $codes->create('Cards', 1);

        $unknown = $codes->redeem('0000-0000-0000-0', 'member-7', 'a');
        self::assertSame(RedemptionOutcome::Unknown, $unknown->outcome);
        self::assertNull($unknown->batchId);
        self::assertNull($unknown->redeemedAt);
        self::assertSame(RedemptionOutcome::Unknown, $codes->redeem('0000-0000-0000-1', 'member-7', 'a')->outcome);
        self::assertSame(RedemptionOutcome::Unknown, $codes->redeem('not a code', 'member-7', 'a')->outcome);
    }

    public function testAnExpiredBatchRedeemsNothing(): void
    {
        $codes = $this->codes();
        $created = $codes->create('Cards', 1, $this->clock->now()->modify('+1 hour'));
        [$code] = $created->takeCodes();
        $this->clock->sleep(3_600);

        $redemption = $codes->redeem($code, 'member-7', 'a');

        self::assertSame(RedemptionOutcome::Expired, $redemption->outcome);
        self::assertSame($created->batch->id, $redemption->batchId);
    }

    public function testARevokedBatchRedeemsNothing(): void
    {
        $codes = $this->codes();
        $created = $codes->create('Cards', 3);
        [$first, $second] = $created->takeCodes();
        $codes->redeem($first, 'member-7', 'a');
        $this->clock->sleep(10);

        self::assertSame(2, $codes->revoke($created->batch->id));
        self::assertSame('2026-11-13 10:00:10', $codes->batch($created->batch->id)->revokedAt?->format('Y-m-d H:i:s'));

        $redemption = $codes->redeem($second, 'member-7', 'a');
        self::assertSame(RedemptionOutcome::Revoked, $redemption->outcome);
        self::assertSame($created->batch->id, $redemption->batchId);
        self::assertSame(RedemptionOutcome::AlreadyRedeemed, $codes->redeem($first, 'member-7', 'a')->outcome);
    }

    public function testAttemptsAreBoundedBeforeAnyLookup(): void
    {
        $codes = $this->codes(limit: 2);
        [$code] = $codes->create('Cards', 1)->takeCodes();

        $codes->redeem('0000-0000-0000-0', 'member-7', 'client-a');
        $codes->redeem('0000-0000-0000-0', 'member-7', 'client-a');

        $refused = $codes->redeem($code, 'member-7', 'client-a');

        self::assertSame(RedemptionOutcome::TooManyAttempts, $refused->outcome);
        self::assertNull($refused->batchId);
        self::assertNotNull($refused->retryAfter);
        self::assertSame(0, $this->store->counts((string) $this->store->find(new CodeHasher(self::KEY)->candidates(new CodeFormat()->parse($code)))?->batchId)->redeemed);
        self::assertSame(RedemptionOutcome::Redeemed, $codes->redeem($code, 'member-7', 'client-b')->outcome);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRedeemers(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['  '];
        yield 'an email address' => ['alice@example.com'];
        yield 'too long' => [str_repeat('a', 192)];
    }

    #[DataProvider('invalidRedeemers')]
    public function testTheRedeemerIsAStableIdentifier(string $redeemer): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('A redeemer is a stable identifier of 1 to 191 bytes, never an email address.');

        $this->codes()->redeem('0000-0000-0000-0', $redeemer, 'a');
    }

    public function testARefusedRedeemerSpendsNoAttempt(): void
    {
        $codes = $this->codes(limit: 1);

        try {
            $codes->redeem('0000-0000-0000-0', 'a@b', 'client-a');
        } catch (InvalidConfiguration) {
        }

        self::assertSame(RedemptionOutcome::Unknown, $codes->redeem('0000-0000-0000-0', str_repeat('a', 191), 'client-a')->outcome);
    }

    public function testAnUnknownBatchIsNamed(): void
    {
        $codes = $this->codes();

        foreach ([static fn (): Batch => $codes->batch('nope'), static fn (): BatchCounts => $codes->counts('nope'), static fn (): int => $codes->revoke('nope')] as $call) {
            try {
                $call();
                self::fail('An unknown batch was answered.');
            } catch (UnknownBatch $exception) {
                self::assertSame('No batch of codes has the identifier "nope".', $exception->getMessage());
            }
        }
    }

    public function testTheDefaultGeneratorIsSecure(): void
    {
        $codes = new AccessCodes($this->store, new CodeHasher(self::KEY), $this->limiter(10), $this->clock);

        self::assertCount(5, array_unique($codes->create('Cards', 5)->takeCodes()));
    }

    public function testCodesAreDrawnUntilTheyAreDistinct(): void
    {
        $format = new CodeFormat(new CodeAlphabet('0123456789ABCDEF'), 10);
        $codes = new AccessCodes($this->store, new CodeHasher(self::KEY), $this->limiter(10), $this->clock, $format, new Randomizer(new Xoshiro256StarStar(3)));

        self::assertCount(1_000, array_unique($codes->create('Cards', 1_000)->takeCodes()));
    }

    private function codes(int $limit = 100): AccessCodes
    {
        return new AccessCodes($this->store, new CodeHasher(self::KEY), $this->limiter($limit), $this->clock, new CodeFormat(), new Randomizer(new Xoshiro256StarStar(11)));
    }

    private function limiter(int $limit): RateLimiterFactory
    {
        return new RateLimiterFactory(['id' => 'codes', 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '1 minute'], new InMemoryStorage());
    }
}
