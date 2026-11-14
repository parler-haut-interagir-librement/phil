<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application;

use Phil\PrivacyBundle\Application\ConfiguredRetentionSchedule;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Retention\AccessResponse;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Retention\Mechanism;
use Phil\PrivacyBundle\Domain\Retention\RetentionPeriodKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfiguredRetentionSchedule::class)]
final class ConfiguredRetentionScheduleTest extends TestCase
{
    public function testEveryFieldOfALineIsRead(): void
    {
        $rule = new ConfiguredRetentionSchedule(['R-1' => $this->line('P30D')])->load()->rule('R-1');

        self::assertSame('trash', $rule->store);
        self::assertSame('email', $rule->categories[0]->value);
        self::assertSame('P30D', $rule->period->duration);
        self::assertSame(Mechanism::Rotation, $rule->mechanism);
        self::assertSame(ErasureEffect::Survives, $rule->erasure);
        self::assertSame(AccessResponse::AsCategory, $rule->access);
    }

    public function testTheProcessingOfALineIsRead(): void
    {
        $line = $this->line('P30D') + ['purpose' => 'Billing', 'recipients' => ['Accountant'], 'processors' => [['name' => 'Host', 'location' => 'Canada']], 'location' => 'Quebec'];
        $processing = new ConfiguredRetentionSchedule(['R-1' => $line])->load()->rule('R-1')->processing;

        self::assertSame('Billing', $processing->purpose);
        self::assertSame(['Accountant'], $processing->recipients);
        self::assertCount(1, $processing->processors);
        self::assertSame('Host (Canada)', $processing->processors[0]->toString());
        self::assertSame('Quebec', $processing->location);
    }

    public function testALineWithoutProcessingHasAnEmptyOne(): void
    {
        $processing = new ConfiguredRetentionSchedule(['R-1' => $this->line('P30D')])->load()->rule('R-1')->processing;

        self::assertNull($processing->purpose);
        self::assertSame([], $processing->recipients);
        self::assertSame([], $processing->processors);
        self::assertNull($processing->location);
    }

    public function testANumericIdentifierIsAString(): void
    {
        self::assertTrue(new ConfiguredRetentionSchedule([7 => $this->line('P30D')])->load()->has('7'));
    }

    public function testTheOtherShapesOfAPeriod(): void
    {
        $schedule = new ConfiguredRetentionSchedule([
            'A' => $this->line('indefinite'),
            'B' => $this->line(['until_event' => 'closed']),
            'C' => $this->line(['not_yet_fixed' => 'the policy']),
        ])->load();

        self::assertSame(RetentionPeriodKind::Indefinite, $schedule->rule('A')->period->kind);
        self::assertSame('closed', $schedule->rule('B')->period->note);
        self::assertSame('the policy', $schedule->rule('C')->period->note);
        self::assertSame(RetentionPeriodKind::NotYetFixed, $schedule->rule('C')->period->kind);
    }

    public function testAnyOtherPeriodIsRefused(): void
    {
        foreach ([5, ['other' => 'x'], ['until_event' => 5], ['not_yet_fixed' => 5]] as $period) {
            try {
                new ConfiguredRetentionSchedule(['A' => $this->line($period)]);
                self::fail('A period of another shape must not pass.');
            } catch (InvalidPrivacyValue $invalidPrivacyValue) {
                self::assertStringContainsString('A retention period is', $invalidPrivacyValue->getMessage());
            }
        }
    }

    /**
     * @return array{store: string, categories: list<string>, period: mixed, mechanism: string, erasure: string, access: string}
     */
    private function line(mixed $period): array
    {
        return ['store' => 'trash', 'categories' => ['email'], 'period' => $period, 'mechanism' => 'rotation', 'erasure' => 'survives', 'access' => 'as_category'];
    }
}
