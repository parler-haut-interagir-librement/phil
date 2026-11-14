<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Retention;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Retention\AccessResponse;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Retention\Mechanism;
use Phil\PrivacyBundle\Domain\Retention\RetentionPeriod;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetentionRule::class)]
final class RetentionRuleTest extends TestCase
{
    public function testALineOfTheScheduleKeepsWhatItStates(): void
    {
        $rule = new RetentionRule('R-056', 'Application logs', [DataCategory::fromString('account_id'), DataCategory::fromString('ip')], RetentionPeriod::fixed('P90D'), Mechanism::Rotation, ErasureEffect::AwaitsExpiry, AccessResponse::AsCategory);

        self::assertSame('R-056', $rule->id);
        self::assertSame('Application logs', $rule->store);
        self::assertCount(2, $rule->categories);
        self::assertTrue($rule->holdsPersonalInformation());
    }

    public function testAStoreWithNoCategoryHoldsNoPersonalInformation(): void
    {
        $rule = new RetentionRule('R-1', 'Metrics', [], RetentionPeriod::fixed('P30D'), Mechanism::Job, ErasureEffect::Survives, AccessResponse::NotReturned);

        self::assertFalse($rule->holdsPersonalInformation());
    }

    public function testAMalformedIdentifierIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        new RetentionRule('rule one', 'Store', [], RetentionPeriod::indefinite(), Mechanism::Manual, ErasureEffect::Survives, AccessResponse::Returned);
    }

    public function testARuleWithoutAStoreIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        new RetentionRule('R-1', ' ', [], RetentionPeriod::indefinite(), Mechanism::Manual, ErasureEffect::Survives, AccessResponse::Returned);
    }

    public function testACategoryListedTwiceIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);

        new RetentionRule('R-1', 'Store', [DataCategory::fromString('email'), DataCategory::fromString('email')], RetentionPeriod::indefinite(), Mechanism::Manual, ErasureEffect::Survives, AccessResponse::Returned);
    }

    public function testAPurgeJobOnAnIndefinitePeriodIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('could never purge anything');

        new RetentionRule('R-1', 'Store', [], RetentionPeriod::indefinite(), Mechanism::Job, ErasureEffect::Survives, AccessResponse::Returned);
    }

    public function testAwaitingExpiryWithoutAFixedPeriodIsRefused(): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('no fixed period');

        new RetentionRule('R-1', 'Store', [], RetentionPeriod::untilEvent('life of the account'), Mechanism::Event, ErasureEffect::AwaitsExpiry, AccessResponse::Returned);
    }
}
