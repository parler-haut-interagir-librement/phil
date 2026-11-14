<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\Model\LearningActivity;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\MemberId;
use Phil\CertificatesBundle\Domain\ValueObject\Period;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(Credits::class)]
#[CoversClass(MemberId::class)]
#[CoversClass(Period::class)]
#[CoversClass(LearningActivity::class)]
final class ContinuingEducationValuesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function credits(): iterable
    {
        yield 'whole' => ['3', 300, '3'];
        yield 'a half' => ['1.5', 150, '1.5'];
        yield 'a comma' => ['0,25', 25, '0.25'];
        yield 'a tenth' => ['2.05', 205, '2.05'];
        yield 'zeros after the point' => ['4.00', 400, '4'];
        yield 'the largest' => ['9999999.99', Credits::MAX, '9999999.99'];
        yield 'none' => ['0', 0, '0'];
    }

    #[DataProvider('credits')]
    public function testCreditsAreReadToTheHundredthAndWrittenBack(string $typed, int $hundredths, string $formatted): void
    {
        $credits = Credits::fromString($typed);

        self::assertSame($hundredths, $credits->hundredths);
        self::assertSame($formatted, $credits->formatted());
        self::assertSame($hundredths, Credits::fromHundredths($hundredths)->hundredths);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadableCredits(): iterable
    {
        yield 'empty' => [''];
        yield 'negative' => ['-1'];
        yield 'three decimals' => ['1.255'];
        yield 'eight digits' => ['10000000'];
        yield 'a point alone' => ['1.'];
        yield 'a letter' => ['1h'];
        yield 'a new line after' => ["1\n"];
        yield 'a space before' => [' 1'];
    }

    #[DataProvider('unreadableCredits')]
    public function testCreditsThatCannotBeReadAreRefused(string $typed): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('Credits are a number with up to two decimals, such as 1.5; "%s" is not.', $typed));

        Credits::fromString($typed);
    }

    public function testHundredthsOutOfRangeAreRefused(): void
    {
        foreach ([-1, Credits::MAX + 1] as $hundredths) {
            try {
                Credits::fromHundredths($hundredths);
                self::fail('Refused.');
            } catch (InvalidValue $invalidValue) {
                self::assertSame(\sprintf('Credits are from 0 to 9999999.99; %d hundredths are not.', $hundredths), $invalidValue->getMessage());
            }
        }
    }

    public function testCreditsAddUpAndCompare(): void
    {
        $gathered = Credits::fromString('7.5')->plus(Credits::fromString('2.5'));

        self::assertSame(1000, $gathered->hundredths);
        self::assertSame(0, Credits::zero()->hundredths);
        self::assertTrue($gathered->covers(Credits::fromString('10')));
        self::assertFalse($gathered->covers(Credits::fromString('10.01')));
        self::assertSame('0.01', $gathered->missingTo(Credits::fromString('10.01'))->formatted());
        self::assertSame('0', $gathered->missingTo(Credits::fromString('9'))->formatted());
        self::assertSame('0', $gathered->missingTo(Credits::fromString('10'))->formatted());

        $this->expectException(InvalidValue::class);
        Credits::fromHundredths(Credits::MAX)->plus(Credits::fromHundredths(1));
    }

    public function testAMemberIsAStableIdentifier(): void
    {
        self::assertSame('0190f1d2-3c4b-7a8e', MemberId::fromString('0190f1d2-3c4b-7a8e')->value);
        self::assertSame(str_repeat('a', 191), MemberId::fromString(str_repeat('a', 191))->value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidMembers(): iterable
    {
        $shape = 'The identifier of a member has from 1 to 191 characters, without spaces nor control characters.';

        yield 'empty' => ['', $shape];
        yield 'blank' => [' ', $shape];
        yield 'too long' => [str_repeat('a', 192), $shape];
        yield 'a space inside' => ['ada lovelace', $shape];
        yield 'a tab' => ["ada\t", $shape];
        yield 'a delete' => ["ada\x7F", $shape];
        yield 'an e-mail address' => ['ada@example.org', 'The identifier of a member is a stable identifier, not an e-mail address.'];
    }

    #[DataProvider('invalidMembers')]
    public function testAMemberThatIsNoStableIdentifierIsRefused(string $member, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        MemberId::fromString($member);
    }

    public function testAPeriodIncludesItsStartAndExcludesItsEnd(): void
    {
        $period = new Period(new DateTimeImmutable('2026-01-01 UTC'), new DateTimeImmutable('2027-01-01 UTC'));

        self::assertTrue($period->contains(new DateTimeImmutable('2026-01-01 UTC')));
        self::assertTrue($period->contains(new DateTimeImmutable('2026-12-31 23:59:59 UTC')));
        self::assertFalse($period->contains(new DateTimeImmutable('2027-01-01 UTC')));
        self::assertFalse($period->contains(new DateTimeImmutable('2025-12-31 23:59:59 UTC')));

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A period ends after it starts.');

        new Period(new DateTimeImmutable('2026-01-01 UTC'), new DateTimeImmutable('2026-01-01 UTC'));
    }

    public function testAnActivityIsRecordedForAMember(): void
    {
        $course = Course::define('first-aid', 'First aid', '', 'Pass.');
        $other = Course::define('cpr', 'CPR', '', 'Pass.');
        $activity = LearningActivity::record(MemberId::fromString('member-1'), 'Triage', Credits::fromString('1.5'), new DateTimeImmutable('2026-11-08 UTC'), $course, 'workshop-1');

        self::assertTrue(Uuid::isValid($activity->id));
        self::assertSame(['member-1', 'Triage', 150, '2026-11-08', 'workshop-1'], [$activity->member, $activity->title, $activity->credits->hundredths, $activity->completedAt->format('Y-m-d'), $activity->source]);
        self::assertSame($course, $activity->course);
        self::assertTrue($activity->countsToward($course));
        self::assertFalse($activity->countsToward($other));

        $general = LearningActivity::record(MemberId::fromString('member-1'), str_repeat('é', 255), Credits::fromString('0.01'), new DateTimeImmutable('2026-11-08 UTC'), source: str_repeat('é', 255));
        self::assertNull($general->course);
        self::assertTrue($general->countsToward($other));
        self::assertNull(LearningActivity::record(MemberId::fromString('member-1'), 'T', Credits::fromString('1'), new DateTimeImmutable())->source);
    }

    /**
     * @return iterable<string, array{string, string, ?string, string}>
     */
    public static function invalidActivities(): iterable
    {
        yield 'a blank title' => [' ', '1', null, 'The title of an activity has from 1 to 255 characters.'];
        yield 'a long title' => [str_repeat('é', 256), '1', null, 'The title of an activity has from 1 to 255 characters.'];
        yield 'no credits' => ['Triage', '0', null, 'An activity earns credits.'];
        yield 'a blank source' => ['Triage', '1', ' ', 'The source of an activity has from 1 to 255 characters.'];
        yield 'a long source' => ['Triage', '1', str_repeat('é', 256), 'The source of an activity has from 1 to 255 characters.'];
    }

    #[DataProvider('invalidActivities')]
    public function testAnActivityThatDoesNotHoldIsRefused(string $title, string $credits, ?string $source, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        LearningActivity::record(MemberId::fromString('member-1'), $title, Credits::fromString($credits), new DateTimeImmutable(), source: $source);
    }
}
