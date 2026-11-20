<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Register;

use Generator;
use Phil\PrivacyBundle\Application\Register\ProcessingActivity;
use Phil\PrivacyBundle\Application\Register\ProcessingRegister;
use Phil\PrivacyBundle\Application\Register\RegisterEntry;
use Phil\PrivacyBundle\Domain\Retention\AccessResponse;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Retention\Mechanism;
use Phil\PrivacyBundle\Domain\Retention\Processing;
use Phil\PrivacyBundle\Domain\Retention\Processor;
use Phil\PrivacyBundle\Domain\Retention\RetentionPeriod;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;
use Phil\PrivacyBundle\Domain\Retention\RetentionSchedule;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\FixedScheduleSource;
use Phil\PrivacyBundle\Tests\Unit\Application\Register\Fixture\Archive;
use Phil\PrivacyBundle\Tests\Unit\Application\Register\Fixture\Ledger;
use Phil\PrivacyBundle\Tests\Unit\Domain\Declaration\Fixture\BaseModel;
use Phil\PrivacyBundle\Tests\Unit\Domain\Declaration\Fixture\Member;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(ProcessingRegister::class)]
#[CoversClass(RegisterEntry::class)]
#[CoversClass(ProcessingActivity::class)]
final class ProcessingRegisterTest extends TestCase
{
    public function testTheRegisterIsDrawnFromTheDeclarationsAndTheSchedule(): void
    {
        $schedule = new RetentionSchedule([
            new RetentionRule('R-002', 'members', [DataCategory::fromString('email')], RetentionPeriod::fixed('P24M'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned),
            new RetentionRule('R-009', 'logs', [DataCategory::fromString('ip')], RetentionPeriod::untilEvent('account closed'), Mechanism::Rotation, ErasureEffect::Pseudonymised, AccessResponse::AsCategory),
        ]);

        $entries = new ProcessingRegister(new FixedScheduleSource($schedule))->entries([Member::class, stdClass::class, BaseModel::class, Member::class]);

        self::assertSame([
            ['category' => 'email', 'class' => Member::class, 'property' => 'email', 'retention' => 'R-002', 'store' => 'members', 'period' => 'P24M', 'mechanism' => 'job', 'erasure' => 'deleted', 'personal_data_left' => false],
            ['category' => 'ip', 'class' => BaseModel::class, 'property' => 'createdFrom', 'retention' => 'R-009', 'store' => 'logs', 'period' => 'until_event', 'mechanism' => 'rotation', 'erasure' => 'pseudonymised', 'personal_data_left' => true],
            ['category' => 'person_name', 'class' => Member::class, 'property' => 'displayName', 'retention' => null, 'store' => null, 'period' => null, 'mechanism' => null, 'erasure' => null, 'personal_data_left' => null],
        ], array_map(static fn (RegisterEntry $entry): array => $entry->toArray(), $entries));
        self::assertSame('email', $entries[0]->field->category->value);
        self::assertSame('members', $entries[0]->rule?->store);
    }

    public function testWithoutAScheduleOrARuleItHoldsTheRuleNamesOnly(): void
    {
        $alone = new ProcessingRegister()->entries([Member::class]);
        self::assertSame(['R-002', null, null], [$alone[0]->field->retention, $alone[0]->rule, $alone[0]->toArray()['store']]);

        $missing = new ProcessingRegister(new FixedScheduleSource(new RetentionSchedule([])))->entries([Member::class]);
        self::assertSame([null, null, null], array_map(static fn (RegisterEntry $entry): ?RetentionRule => $entry->rule, $missing));
        self::assertSame([], new ProcessingRegister()->entries([]));
    }

    public function testTheActivitiesAreTheRulesThatHoldPersonalInformationWithTheFieldsThatNameThem(): void
    {
        $schedule = new RetentionSchedule([
            new RetentionRule('R-002', 'members', [DataCategory::fromString('email')], RetentionPeriod::fixed('P24M'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned, new Processing(
                'Running the membership',
                ['Membership team'],
                [new Processor('Host', 'Canada'), new Processor('Mail', 'United States')],
                'Canada',
            )),
            new RetentionRule('R-005', 'cache', [], RetentionPeriod::fixed('P1D'), Mechanism::Ttl, ErasureEffect::AwaitsExpiry, AccessResponse::NotReturned),
            new RetentionRule('R-009', 'logs', [], RetentionPeriod::untilEvent('account closed'), Mechanism::Rotation, ErasureEffect::Pseudonymised, AccessResponse::AsCategory),
        ]);

        $register = new ProcessingRegister(new FixedScheduleSource($schedule));
        $activities = $register->activities([Member::class, BaseModel::class]);

        self::assertSame([
            [
                'rule' => 'R-002',
                'store' => 'members',
                'categories' => ['email'],
                'purpose' => 'Running the membership',
                'recipients' => ['Membership team'],
                'processors' => [['name' => 'Host', 'location' => 'Canada'], ['name' => 'Mail', 'location' => 'United States']],
                'location' => 'Canada',
                'period' => 'P24M',
                'fields' => [Member::class.'::$email'],
            ],
            [
                'rule' => 'R-009',
                'store' => 'logs',
                'categories' => [],
                'purpose' => null,
                'recipients' => [],
                'processors' => [],
                'location' => null,
                'period' => 'until_event',
                'fields' => [BaseModel::class.'::$createdFrom'],
            ],
        ], array_map(static fn (ProcessingActivity $activity): array => $activity->toArray(), $activities));
        self::assertTrue($activities[0]->isComplete());
        self::assertFalse($activities[1]->isComplete());
        self::assertSame([
            'Rule R-009 (logs) holds personal information and states no purpose.',
            'Rule R-009 (logs) holds personal information and states no location.',
        ], $register->gaps([Member::class, BaseModel::class]));
        self::assertSame([], new ProcessingRegister()->activities([Member::class]));
    }

    public function testAFieldThatNamesAMissingRuleIsAGapOnlyWhenThereIsASchedule(): void
    {
        $schedule = new RetentionSchedule([
            new RetentionRule('R-002', 'members', [DataCategory::fromString('email')], RetentionPeriod::fixed('P24M'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned, new Processing('Membership', location: 'Canada')),
        ]);

        self::assertSame(
            [BaseModel::class.'::$createdFrom names the retention rule R-009, which the schedule does not hold.'],
            new ProcessingRegister(new FixedScheduleSource($schedule))->gaps([Member::class, BaseModel::class]),
        );
        self::assertSame([], new ProcessingRegister()->gaps([Member::class, BaseModel::class]));
    }

    public function testTheFieldsOfAnActivityAreSortedByClassThenProperty(): void
    {
        $schedule = new RetentionSchedule([
            new RetentionRule('R-100', 'records', [], RetentionPeriod::fixed('P1Y'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned, new Processing('Records', location: 'Canada')),
        ]);

        $activities = new ProcessingRegister(new FixedScheduleSource($schedule))->activities([Ledger::class, Archive::class]);

        self::assertCount(1, $activities);
        self::assertSame([Archive::class.'::$beta', Archive::class.'::$zone', Ledger::class.'::$alpha'], $activities[0]->toArray()['fields']);
    }

    public function testEveryClassGivenIsSearchedForGaps(): void
    {
        $schedule = new RetentionSchedule([
            new RetentionRule('R-100', 'records', [], RetentionPeriod::fixed('P1Y'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned, new Processing('Records', location: 'Canada')),
        ]);

        self::assertSame(
            [Ledger::class.'::$lost names the retention rule R-404, which the schedule does not hold.'],
            new ProcessingRegister(new FixedScheduleSource($schedule))->gaps([Archive::class, Ledger::class]),
        );
    }

    public function testAGeneratorOfClassesIsReadOnce(): void
    {
        $schedule = new RetentionSchedule([
            new RetentionRule('R-002', 'members', [DataCategory::fromString('email')], RetentionPeriod::fixed('P24M'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned),
        ]);
        $classes = (static function (): Generator {
            yield Member::class;
        })();

        self::assertSame([
            'Rule R-002 (members) holds personal information and states no purpose.',
            'Rule R-002 (members) holds personal information and states no location.',
            BaseModel::class.'::$createdFrom names the retention rule R-009, which the schedule does not hold.',
        ], new ProcessingRegister(new FixedScheduleSource($schedule))->gaps($classes));
    }
}
