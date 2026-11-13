<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Unit\Application;

use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\PollRules;
use Phil\PollBundle\Application\BallotForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BallotForm::class)]
final class BallotFormTest extends TestCase
{
    public function testASingleBallotIsItsOneChoice(): void
    {
        $rules = PollRules::single(['tea', 'coffee'], abstention: true);

        self::assertSame(['coffee'], BallotForm::choices($rules, ['choice' => 'coffee', 'choices' => ['tea']]));
        self::assertSame([], BallotForm::choices($rules, ['choice' => '']));
        self::assertSame([], BallotForm::choices($rules, []));
    }

    public function testAMultipleBallotIsItsListOfChoices(): void
    {
        $rules = PollRules::multiple(['tea', 'coffee', 'cocoa']);

        self::assertSame(['cocoa', 'tea'], BallotForm::choices($rules, ['choices' => ['cocoa', 'tea'], 'choice' => 'coffee']));
        self::assertSame([], BallotForm::choices($rules, []));
    }

    public function testARankedBallotListsTheRankedOptionsTheLowestRankFirst(): void
    {
        $rules = PollRules::ranked(['tea', 'coffee', 'cocoa', 'water']);

        self::assertSame(['cocoa', 'tea', 'coffee'], BallotForm::choices($rules, ['ranks' => ['tea' => '2', 'coffee' => '10', 'cocoa' => '1', 'water' => '']]));
        self::assertSame(['water', 'tea'], BallotForm::choices($rules, ['ranks' => ['tea' => '999', 'water' => '9']]));
        self::assertSame([], BallotForm::choices($rules, ['ranks' => ['tea' => '']]));
        self::assertSame(['coffee'], BallotForm::choices($rules, ['ranks' => ['tea' => '', 'coffee' => '1']]));
        self::assertSame([], BallotForm::choices($rules, []));
    }

    /**
     * @return iterable<string, array{PollRules, array<mixed>, string}>
     */
    public static function badFields(): iterable
    {
        $single = PollRules::single(['tea', 'coffee']);
        $multiple = PollRules::multiple(['tea', 'coffee']);
        $ranked = PollRules::ranked(['tea', 'coffee', '7']);

        yield 'a single choice as a list' => [$single, ['choice' => ['tea']], 'The choice of a single ballot is one option.'];
        yield 'multiple choices as text' => [$multiple, ['choices' => 'tea'], 'The choices of a multiple ballot are a list of options.'];
        yield 'multiple choices keyed' => [$multiple, ['choices' => ['a' => 'tea']], 'The choices of a multiple ballot are a list of options.'];
        yield 'a multiple choice that is no text' => [$multiple, ['choices' => ['tea', ['coffee']]], 'The choices of a multiple ballot are a list of options.'];
        yield 'ranks as text' => [$ranked, ['ranks' => '1'], 'The ranks of a ranked ballot are given option by option.'];
        yield 'a rank that is no text' => [$ranked, ['ranks' => ['tea' => 1]], 'The ranks of a ranked ballot are given option by option.'];
        yield 'a rank of an unknown option' => [$ranked, ['ranks' => ['milk' => '1']], 'The ranks of a ranked ballot are given option by option.'];
        yield 'a rank of zero' => [$ranked, ['ranks' => ['tea' => '0']], 'A rank is a number from 1 to 3.'];
        yield 'a rank of four digits' => [$ranked, ['ranks' => ['tea' => '1000']], 'A rank is a number from 1 to 3.'];
        yield 'a rank that is not a number' => [$ranked, ['ranks' => ['tea' => '1a']], 'A rank is a number from 1 to 3.'];
        yield 'a rank with a sign' => [$ranked, ['ranks' => ['tea' => '+1']], 'A rank is a number from 1 to 3.'];
        yield 'a rank with a line after it' => [$ranked, ['ranks' => ['tea' => "1\n"]], 'A rank is a number from 1 to 3.'];
        yield 'a shared rank' => [$ranked, ['ranks' => ['tea' => '1', '7' => '2', 'coffee' => '1']], 'Two options share the rank 1.'];
    }

    /**
     * @param array<mixed> $fields
     */
    #[DataProvider('badFields')]
    public function testFieldsAFormDoesNotSendAreRefused(PollRules $rules, array $fields, string $message): void
    {
        $this->expectException(InvalidBallot::class);
        $this->expectExceptionMessageIsOrContains($message);

        BallotForm::choices($rules, $fields);
    }

    public function testAnOptionNamedByDigitsIsRanked(): void
    {
        self::assertSame(['7', 'tea'], BallotForm::choices(PollRules::ranked(['tea', '7']), ['ranks' => ['tea' => '2', '7' => '1']]));
    }
}
