<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\Poll\BallotKind;
use Phil\Poll\PollRules;
use Phil\PollBundle\Domain\Exception\InvalidPollDefinition;
use Phil\PollBundle\Domain\Exception\PollBundleException;
use Phil\PollBundle\Domain\Model\Poll;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Poll::class)]
#[CoversClass(PollOption::class)]
#[CoversClass(InvalidPollDefinition::class)]
final class PollTest extends TestCase
{
    public function testANewPoll(): void
    {
        $poll = $this->poll(subject: ResourceReference::of('idea', '7'));

        self::assertSame('p1', $poll->id);
        self::assertSame('Which day?', $poll->question());
        self::assertEquals([PollOption::of('sat', 'Saturday'), PollOption::of('sun', 'Sunday'), PollOption::of('mon', 'Monday')], $poll->options());
        self::assertSame('Sunday', $poll->label('sun'));
        self::assertNull($poll->label('tue'));
        self::assertEquals(PollRules::ranked(['sat', 'sun', 'mon'], 1, 2, true), $poll->rules());
        self::assertSame('instant_runoff', $poll->counting());
        self::assertTrue($poll->isAnonymous());
        self::assertSame(ResultsVisibility::AfterClosing, $poll->resultsVisibility());
        self::assertSame('idea:7', $poll->subject()?->toString());
        self::assertSame('user:ada', $poll->createdBy()->toString());
        self::assertTrue($poll->isCreatedBy(ResourceReference::of('user', 'ada')));
        self::assertFalse($poll->isCreatedBy(ResourceReference::of('user', 'bob')));
        self::assertSame('2026-11-08 10:00:00', $poll->createdAt()->format('Y-m-d H:i:s'));
        self::assertSame('+00:00', $poll->createdAt()->format('P'));
        self::assertNull($poll->opensAt());
        self::assertNull($poll->closesAt());
        self::assertNull($poll->closedAt());
        self::assertNull($this->poll()->subject());
    }

    public function testTheRulesOfASingleAndAMultiplePoll(): void
    {
        self::assertEquals(PollRules::single(['a', 'b']), Poll::create('p', 'Q', [PollOption::of('a', 'A'), PollOption::of('b', 'B')], PollRules::single(['a', 'b']), 'choices', false, ResultsVisibility::Always, ResourceReference::of('user', 'ada'), new DateTimeImmutable())->rules());
        $multiple = Poll::create('p', 'Q', [PollOption::of('a', 'A'), PollOption::of('b', 'B'), PollOption::of('c', 'C')], PollRules::multiple(['a', 'b', 'c'], 2, 3), 'choices', false, ResultsVisibility::Always, ResourceReference::of('user', 'ada'), new DateTimeImmutable());
        self::assertSame([BallotKind::Multiple, 2, 3, false], [$multiple->rules()->kind, $multiple->rules()->minChoices, $multiple->rules()->maxChoices, $multiple->rules()->abstention]);
        self::assertFalse($multiple->isAnonymous());
    }

    public function testTheOpeningAndTheClosing(): void
    {
        $poll = $this->poll(opensAt: new DateTimeImmutable('2026-11-09 08:00:00.7 UTC'), closesAt: new DateTimeImmutable('2026-11-10 08:00:00 UTC'));

        self::assertSame('2026-11-09 08:00:00.000000', $poll->opensAt()?->format('Y-m-d H:i:s.u'));
        self::assertSame('2026-11-10 08:00:00', $poll->closesAt()?->format('Y-m-d H:i:s'));
        self::assertFalse($poll->isOpenAt(new DateTimeImmutable('2026-11-09 07:59:59 UTC')));
        self::assertFalse($poll->isClosedAt(new DateTimeImmutable('2026-11-09 07:59:59 UTC')));
        self::assertTrue($poll->isOpenAt(new DateTimeImmutable('2026-11-09 08:00:00 UTC')));
        self::assertTrue($poll->isOpenAt(new DateTimeImmutable('2026-11-10 07:59:59 UTC')));
        self::assertFalse($poll->isClosedAt(new DateTimeImmutable('2026-11-10 07:59:59 UTC')));
        self::assertFalse($poll->isOpenAt(new DateTimeImmutable('2026-11-10 08:00:00 UTC')));
        self::assertTrue($poll->isClosedAt(new DateTimeImmutable('2026-11-10 08:00:00 UTC')));
    }

    public function testAPollWithoutDatesIsOpenUntilClosed(): void
    {
        $poll = $this->poll();
        $now = new DateTimeImmutable('2026-11-08 12:00:00.5 UTC');

        self::assertTrue($poll->isOpenAt($now));
        $poll->close($now);
        self::assertSame('2026-11-08 12:00:00.000000', $poll->closedAt()?->format('Y-m-d H:i:s.u'));
        self::assertFalse($poll->isOpenAt($now));
        self::assertTrue($poll->isClosedAt(new DateTimeImmutable('2020-01-01')));

        $poll->close(new DateTimeImmutable('2026-11-09 00:00:00 UTC'));
        self::assertSame('2026-11-08 12:00:00', $poll->closedAt()?->format('Y-m-d H:i:s'));
    }

    public function testClosingAPollPastItsClosingKeepsItsClosing(): void
    {
        $poll = $this->poll(closesAt: new DateTimeImmutable('2026-11-08 11:00:00 UTC'));

        $poll->close(new DateTimeImmutable('2026-11-08 12:00:00 UTC'));

        self::assertNull($poll->closedAt());
    }

    public function testAPollIsRescheduled(): void
    {
        $poll = $this->poll(closesAt: new DateTimeImmutable('2026-11-08 11:00:00 UTC'));

        $poll->reschedule(null, new DateTimeImmutable('2026-12-01 00:00:00 UTC'));
        self::assertSame([null, '2026-12-01'], [$poll->opensAt(), $poll->closesAt()?->format('Y-m-d')]);

        $poll->reschedule(new DateTimeImmutable('2026-11-30 00:00:00 UTC'), null);
        self::assertSame(['2026-11-30', null], [$poll->opensAt()?->format('Y-m-d'), $poll->closesAt()]);

        $poll->reschedule(new DateTimeImmutable('2026-11-30 00:00:00 UTC'), new DateTimeImmutable('2026-11-30 00:00:01 UTC'));
        self::assertSame('00:00:01', $poll->closesAt()?->format('H:i:s'));

        $this->expectExceptionObject(InvalidPollDefinition::because('A poll closes after it opens.'));

        $poll->reschedule(new DateTimeImmutable('2026-11-30 00:00:00 UTC'), new DateTimeImmutable('2026-11-30 00:00:00.9 UTC'));
    }

    #[TestWith(['', 'Q', 'instant_runoff', 'A poll is identified by 1 to 36 characters.'])]
    #[TestWith(['0123456789012345678901234567890123456', 'Q', 'instant_runoff', 'A poll is identified by 1 to 36 characters.'])]
    #[TestWith(['p', ' ', 'instant_runoff', 'The question of a poll is a UTF-8 text of 1 to 500 characters.'])]
    #[TestWith(['p', "\xFF", 'instant_runoff', 'The question of a poll is a UTF-8 text of 1 to 500 characters.'])]
    #[TestWith(['p', 'Q', 'Borda', 'A counting method is named by 1 to 64 lowercase ASCII letters, digits or "_".'])]
    #[TestWith(['p', 'Q', "borda\n", 'A counting method is named by 1 to 64 lowercase ASCII letters, digits or "_".'])]
    #[TestWith(['p', 'Q', 'mmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmm', 'A counting method is named by 1 to 64 lowercase ASCII letters, digits or "_".'])]
    public function testAnInvalidPollIsRefused(string $id, string $question, string $counting, string $message): void
    {
        $this->expectExceptionObject(InvalidPollDefinition::because($message));

        Poll::create($id, $question, [PollOption::of('a', 'A'), PollOption::of('b', 'B')], PollRules::single(['a', 'b']), $counting, false, ResultsVisibility::Always, ResourceReference::of('user', 'ada'), new DateTimeImmutable());
    }

    public function testTheBoundsOfAPoll(): void
    {
        self::assertSame(500, Poll::MAX_QUESTION);
        self::assertSame(str_repeat('é', 500), $this->poll(question: str_repeat('é', 500))->question());
        self::assertSame(str_repeat('i', 36), Poll::create(str_repeat('i', 36), 'Q', [PollOption::of('a', 'A'), PollOption::of('b', 'B')], PollRules::single(['a', 'b']), str_repeat('m', 64), false, ResultsVisibility::Always, ResourceReference::of('user', 'ada'), new DateTimeImmutable())->id);

        $this->expectException(InvalidPollDefinition::class);

        $this->poll(question: str_repeat('é', 501));
    }

    public function testTheOptionsAreThoseOfTheRules(): void
    {
        $this->expectExceptionObject(InvalidPollDefinition::because('The options of a poll are those of its rules, in their order.'));

        Poll::create('p', 'Q', [PollOption::of('b', 'B'), PollOption::of('a', 'A')], PollRules::single(['a', 'b']), 'choices', false, ResultsVisibility::Always, ResourceReference::of('user', 'ada'), new DateTimeImmutable());
    }

    public function testAPollClosingBeforeItOpensIsRefused(): void
    {
        $this->expectExceptionObject(InvalidPollDefinition::because('A poll closes after it opens.'));

        $this->poll(opensAt: new DateTimeImmutable('2026-11-10'), closesAt: new DateTimeImmutable('2026-11-09'));
    }

    #[TestWith(['', 'Label', 'An option is identified by 1 to 64 ASCII letters, digits, "_" or "-".'])]
    #[TestWith(["a\n", 'Label', 'An option is identified by 1 to 64 ASCII letters, digits, "_" or "-".'])]
    #[TestWith(['a', ' ', 'The label of an option is a UTF-8 text of 1 to 200 characters.'])]
    #[TestWith(['a', "\xFF", 'The label of an option is a UTF-8 text of 1 to 200 characters.'])]
    public function testAnInvalidOptionIsRefused(string $id, string $label, string $message): void
    {
        $this->expectExceptionObject(InvalidPollDefinition::because($message));

        PollOption::of($id, $label);
    }

    public function testTheBoundsOfAnOption(): void
    {
        $option = PollOption::of(str_repeat('k', 64), str_repeat('é', 200));

        self::assertSame([str_repeat('k', 64), str_repeat('é', 200)], [$option->id, $option->label]);
        self::assertSame(200, PollOption::MAX_LABEL);
        self::assertInstanceOf(PollBundleException::class, InvalidPollDefinition::because('x'));
    }

    public function testATooLongIdentifierIsRefused(): void
    {
        $this->expectException(InvalidPollDefinition::class);

        PollOption::of(str_repeat('k', 65), 'L');
    }

    public function testATooLongLabelIsRefused(): void
    {
        $this->expectException(InvalidPollDefinition::class);

        PollOption::of('k', str_repeat('é', 201));
    }

    public function testTheSeatsAreOneToOneFewerThanTheOptions(): void
    {
        $options = [PollOption::of('a', 'A'), PollOption::of('b', 'B'), PollOption::of('c', 'C')];
        $rules = PollRules::ranked(['a', 'b', 'c']);
        $make = static fn (int $seats): Poll => Poll::create('p', 'Q', $options, $rules, 'meek_stv', false, ResultsVisibility::Always, ResourceReference::of('user', 'ada'), new DateTimeImmutable(), seats: $seats);

        self::assertSame([1, 2], [$make(1)->seats(), $make(2)->seats()]);

        foreach ([0, 3] as $seats) {
            try {
                $make($seats);
                self::fail((string) $seats);
            } catch (InvalidPollDefinition $invalidPollDefinition) {
                self::assertSame(\sprintf('A poll fills 1 to 2 seats, one fewer than its options, not %d.', $seats), $invalidPollDefinition->getMessage());
            }
        }
    }

    private function poll(?ResourceReference $subject = null, ?DateTimeImmutable $opensAt = null, ?DateTimeImmutable $closesAt = null, string $question = 'Which day?'): Poll
    {
        return Poll::create(
            'p1',
            $question,
            [PollOption::of('sat', 'Saturday'), PollOption::of('sun', 'Sunday'), PollOption::of('mon', 'Monday')],
            PollRules::ranked(['sat', 'sun', 'mon'], 1, 2, true),
            'instant_runoff',
            true,
            ResultsVisibility::AfterClosing,
            ResourceReference::of('user', 'ada'),
            new DateTimeImmutable('2026-11-08 05:00:00.3 America/Toronto'),
            $subject,
            $opensAt,
            $closesAt,
        );
    }
}
