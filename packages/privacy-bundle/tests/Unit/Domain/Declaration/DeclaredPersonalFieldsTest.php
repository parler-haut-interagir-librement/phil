<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Declaration;

use Phil\PrivacyBundle\Domain\Declaration\DeclaredPersonalFields;
use Phil\PrivacyBundle\Domain\Declaration\PersonalField;
use Phil\PrivacyBundle\Domain\Retention\AccessResponse;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Retention\Mechanism;
use Phil\PrivacyBundle\Domain\Retention\RetentionPeriod;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;
use Phil\PrivacyBundle\Domain\Retention\RetentionSchedule;
use Phil\PrivacyBundle\Tests\Unit\Domain\Declaration\Fixture\BaseModel;
use Phil\PrivacyBundle\Tests\Unit\Domain\Declaration\Fixture\Member;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeclaredPersonalFields::class)]
#[CoversClass(PersonalField::class)]
final class DeclaredPersonalFieldsTest extends TestCase
{
    /**
     * The parent's private property is invisible to reflection on the child; it is still a personal field of
     * every member.
     */
    public function testItReadsTheParentsPrivateDeclarationsFirst(): void
    {
        $fields = DeclaredPersonalFields::of(Member::class);

        self::assertSame(
            [
                [BaseModel::class, 'createdFrom', 'ip', 'R-009'],
                [Member::class, 'email', 'email', 'R-002'],
                [Member::class, 'displayName', 'person_name', null],
            ],
            array_map(static fn (PersonalField $field): array => [$field->class, $field->property, $field->category->value, $field->retention], $fields),
        );
    }

    public function testAnUndeclaredPropertyIsNotAPersonalField(): void
    {
        $properties = array_map(static fn (PersonalField $field): string => $field->property, DeclaredPersonalFields::of(Member::class));

        self::assertNotContains('status', $properties);
    }

    public function testItReportsTheFieldsNoRetentionRuleCovers(): void
    {
        $uncovered = DeclaredPersonalFields::uncoveredIn(Member::class);

        self::assertCount(1, $uncovered);
        self::assertSame('displayName', $uncovered[0]->property);
        self::assertFalse($uncovered[0]->isCoveredByRetention());
    }

    /**
     * `R-009` is cited by the base model but missing from the schedule: a misspelt or removed rule covers nothing.
     */
    public function testAgainstAScheduleARuleItDoesNotDefineCoversNothing(): void
    {
        $schedule = new RetentionSchedule([
            new RetentionRule('R-002', 'members', [], RetentionPeriod::fixed('P1Y'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned),
        ]);

        $uncovered = DeclaredPersonalFields::uncoveredIn(Member::class, $schedule);

        self::assertSame(['createdFrom', 'displayName'], array_map(static fn (PersonalField $field): string => $field->property, $uncovered));
        self::assertTrue($uncovered[0]->isCoveredByRetention());
        self::assertFalse($uncovered[0]->isCoveredBy($schedule));
        self::assertFalse($uncovered[1]->isCoveredBy($schedule));
    }

    public function testAgainstAScheduleThatDefinesEveryCitedRuleOnlyTheUncitedFieldIsReported(): void
    {
        $schedule = new RetentionSchedule([
            new RetentionRule('R-002', 'members', [], RetentionPeriod::fixed('P1Y'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned),
            new RetentionRule('R-009', 'members', [], RetentionPeriod::fixed('P1Y'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned),
        ]);

        $uncovered = DeclaredPersonalFields::uncoveredIn(Member::class, $schedule);

        self::assertCount(1, $uncovered);
        self::assertSame('displayName', $uncovered[0]->property);
    }
}
