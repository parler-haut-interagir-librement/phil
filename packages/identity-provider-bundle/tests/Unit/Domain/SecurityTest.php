<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Security\LockoutPolicy;
use Phil\IdentityProviderBundle\Domain\Security\RecoveryCodes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LockoutPolicy::class)]
#[CoversClass(RecoveryCodes::class)]
#[CoversClass(InvalidValue::class)]
final class SecurityTest extends TestCase
{
    public function testTheLockDoublesFromTheThresholdUpToTheMaximum(): void
    {
        $policy = new LockoutPolicy(3, 60, 300);
        $at = new DateTimeImmutable('2026-11-14 10:00:00');

        self::assertNull($policy->lockedUntil(2, $at));
        self::assertSame('10:01:00', $policy->lockedUntil(3, $at)?->format('H:i:s'));
        self::assertSame('10:02:00', $policy->lockedUntil(4, $at)?->format('H:i:s'));
        self::assertSame('10:04:00', $policy->lockedUntil(5, $at)?->format('H:i:s'));
        self::assertSame('10:05:00', $policy->lockedUntil(6, $at)?->format('H:i:s'));
        self::assertSame('10:05:00', $policy->lockedUntil(100, $at)?->format('H:i:s'));
    }

    public function testTheDefaultsOfTheLock(): void
    {
        $policy = new LockoutPolicy();

        self::assertSame([5, 60, 3600, true], [$policy->threshold, $policy->base, $policy->max, $policy->enabled]);
        new LockoutPolicy(1, 1, 1);
    }

    public function testALockoutTurnedOffNeverLocks(): void
    {
        $policy = new LockoutPolicy(1, 60, 60, false);
        $at = new DateTimeImmutable('2026-11-14 10:00:00');

        self::assertNull($policy->lockedUntil(1, $at));
        self::assertNull($policy->lockedUntil(1000, $at));
        self::assertSame('10:01:00', new LockoutPolicy(1, 60, 60)->lockedUntil(1, $at)?->format('H:i:s'));
    }

    public function testTheLockRefusesNonsense(): void
    {
        foreach ([[0, 60, 60], [1, 0, 60], [1, 61, 60]] as [$threshold, $base, $max]) {
            try {
                new LockoutPolicy($threshold, $base, $max);
                self::fail('Accepted.');
            } catch (InvalidValue $invalidValue) {
                self::assertStringContainsString('lockout', $invalidValue->getMessage());
            }
        }
    }

    public function testRecoveryCodesAreGeneratedReadAndMatchedOnce(): void
    {
        $codes = new RecoveryCodes(str_repeat('k', 32));
        $generated = $codes->generate();

        self::assertCount(10, $generated);
        self::assertCount(10, array_unique($generated));

        foreach ($generated as $code) {
            self::assertMatchesRegularExpression('/^['.RecoveryCodes::ALPHABET.']{5}-['.RecoveryCodes::ALPHABET.']{5}$/D', $code);
        }

        $hashes = array_map($codes->hash(...), $generated);
        $hashes[3] = null;

        self::assertSame(2, $codes->match(strtolower(str_replace('-', ' ', $generated[2])), $hashes));
        self::assertNull($codes->match($generated[3], $hashes));
        self::assertNull($codes->match('AAAAA-AAAAA', $hashes));
        self::assertSame(0, $codes->match($generated[0], [$hashes[0], $hashes[0]]));
        self::assertCount(3, $codes->generate(3));
        self::assertSame('ABCDE12345', RecoveryCodes::normalize('abcde-12 345'));
        self::assertNotSame($codes->hash('ABCDE-12345'), new RecoveryCodes(str_repeat('l', 32))->hash('ABCDE-12345'));
    }

    public function testTheKeyOfRecoveryCodesHolds32Bytes(): void
    {
        $this->expectException(InvalidValue::class);

        new RecoveryCodes(str_repeat('k', 31));
    }
}
