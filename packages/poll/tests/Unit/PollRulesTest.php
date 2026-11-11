<?php

declare(strict_types=1);

namespace Phil\Poll\Tests\Unit;

use Phil\Poll\Ballot;
use Phil\Poll\BallotKind;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\Exception\PollException;
use Phil\Poll\PollRules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(PollRules::class)]
#[CoversClass(Ballot::class)]
#[CoversClass(InvalidPoll::class)]
#[CoversClass(InvalidBallot::class)]
final class PollRulesTest extends TestCase
{
    public function testTheFactoriesSetTheBounds(): void
    {
        $single = PollRules::single(['a', 'b']);
        self::assertSame([BallotKind::Single, ['a', 'b'], 1, 1, false], [$single->kind, $single->options, $single->minChoices, $single->maxChoices, $single->abstention]);
        self::assertTrue(PollRules::single(['a', 'b'], true)->abstention);

        $multiple = PollRules::multiple(['a', 'b', 'c']);
        self::assertSame([BallotKind::Multiple, 1, 3, false], [$multiple->kind, $multiple->minChoices, $multiple->maxChoices, $multiple->abstention]);
        $bounded = PollRules::multiple(['a', 'b', 'c'], 2, 2, true);
        self::assertSame([2, 2, true], [$bounded->minChoices, $bounded->maxChoices, $bounded->abstention]);

        $ranked = PollRules::ranked(['a', 'b', 'c']);
        self::assertSame([BallotKind::Ranked, 1, 3, false], [$ranked->kind, $ranked->minChoices, $ranked->maxChoices, $ranked->abstention]);
        $partial = PollRules::ranked(['a', 'b', 'c'], 2, 2, true);
        self::assertSame([2, 2, true], [$partial->minChoices, $partial->maxChoices, $partial->abstention]);

        $restored = PollRules::of(BallotKind::Multiple, ['x', 'y', 'z'], 1, 2);
        self::assertSame([BallotKind::Multiple, ['x', 'y', 'z'], 1, 2, false], [$restored->kind, $restored->options, $restored->minChoices, $restored->maxChoices, $restored->abstention]);
    }

    public function testTheOptionsAreBounded(): void
    {
        self::assertSame(100, PollRules::MAX_OPTIONS);
        self::assertSame(64, PollRules::MAX_OPTION_LENGTH);

        $hundred = array_map(static fn (int $i): string => 'o'.$i, range(1, 100));
        self::assertCount(100, PollRules::single($hundred)->options);
        self::assertSame([str_repeat('k', 64), 'A-z_9', '12'], PollRules::single([str_repeat('k', 64), 'A-z_9', '12'])->options);

        foreach ([['a'], [...$hundred, 'o101']] as $options) {
            try {
                PollRules::single($options);
                self::fail('Accepted '.\count($options).' options.');
            } catch (InvalidPoll $invalidPoll) {
                self::assertSame(\sprintf('A poll has 2 to 100 options, not %d.', \count($options)), $invalidPoll->getMessage());
            }
        }
    }

    #[TestWith([''])]
    #[TestWith(['a b'])]
    #[TestWith(['é'])]
    #[TestWith(["a\n"])]
    #[TestWith(['kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk'])]
    public function testAnOptionIsAnIdentifier(string $option): void
    {
        $this->expectExceptionObject(InvalidPoll::because(\sprintf('An option is identified by 1 to 64 ASCII letters, digits, "_" or "-", not "%s".', $option)));

        PollRules::single(['a', $option]);
    }

    public function testAnOptionIsDeclaredOnce(): void
    {
        $this->expectExceptionObject(InvalidPoll::because('The option "a" is declared twice.'));

        PollRules::single(['a', 'b', 'a']);
    }

    #[TestWith([0, 2])]
    #[TestWith([3, 2])]
    #[TestWith([1, 4])]
    public function testTheBoundsAreChecked(int $min, int $max): void
    {
        $this->expectExceptionObject(InvalidPoll::because(\sprintf('A ballot holds at least 1 choice and at most the 3 options, the minimum not above the maximum; not %d to %d.', $min, $max)));

        PollRules::multiple(['a', 'b', 'c'], $min, $max);
    }

    public function testASingleBallotHoldsOneChoice(): void
    {
        $this->expectExceptionObject(InvalidPoll::because('A single ballot holds exactly one choice.'));

        PollRules::of(BallotKind::Single, ['a', 'b'], 1, 2);
    }

    public function testTheOptionsAreKnown(): void
    {
        $rules = PollRules::single(['a', 'b']);

        self::assertTrue($rules->has('a'));
        self::assertFalse($rules->has('c'));
        self::assertInstanceOf(PollException::class, InvalidPoll::because('x'));
    }

    public function testAValidBallotKeepsItsOrder(): void
    {
        $ballot = PollRules::ranked(['a', 'b', 'c'])->ballot(['c', 'a']);

        self::assertSame(['c', 'a'], $ballot->choices);
        self::assertFalse($ballot->isAbstention());
        self::assertSame(['b'], PollRules::single(['a', 'b'])->ballot(['b'])->choices);
        self::assertSame(['a', 'b', 'c'], PollRules::multiple(['a', 'b', 'c'], 3)->ballot(['a', 'b', 'c'])->choices);
    }

    public function testABlankBallotIsAnAbstentionWhenAccepted(): void
    {
        $ballot = PollRules::single(['a', 'b'], true)->ballot([]);

        self::assertSame([], $ballot->choices);
        self::assertTrue($ballot->isAbstention());
        self::assertTrue(Ballot::of([])->isAbstention());
        self::assertSame(['x'], Ballot::of(['x'])->choices);

        $this->expectExceptionObject(InvalidBallot::because('This poll accepts no blank ballot.'));

        PollRules::single(['a', 'b'])->ballot([]);
    }

    /**
     * @param array<mixed> $choices
     */
    #[TestWith([['x' => 'a'], 'The choices of a ballot are a list.'])]
    #[TestWith([['d'], '"d" is not an option of this poll.'])]
    #[TestWith([[1], 'A choice is the identifier of an option, not int.'])]
    #[TestWith([[['a']], 'A choice is the identifier of an option, not array.'])]
    #[TestWith([['a', 'a'], 'The option "a" is chosen twice.'])]
    #[TestWith([['a'], 'A ballot of this poll holds 2 to 3 choices, not 1.'])]
    public function testAnInvalidBallotIsRefused(array $choices, string $message): void
    {
        $this->expectExceptionObject(InvalidBallot::because($message));

        PollRules::multiple(['a', 'b', 'c'], 2)->ballot($choices);
    }

    public function testTheMessageOfFixedBounds(): void
    {
        $this->expectExceptionObject(InvalidBallot::because('A ballot of this poll holds 1 choice(s), not 2.'));

        PollRules::single(['a', 'b'])->ballot(['a', 'b']);
    }

    public function testTooManyChoicesAreRefused(): void
    {
        $this->expectExceptionObject(InvalidBallot::because('A ballot of this poll holds 1 to 2 choices, not 3.'));

        PollRules::ranked(['a', 'b', 'c'], 1, 2)->ballot(['a', 'b', 'c']);
    }
}
