<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Unit\Domain;

use Phil\SurveyBundle\Domain\Logic\AnswerCheck;
use Phil\SurveyBundle\Domain\Status\QuestionType;
use Phil\SurveyBundle\Domain\ValueObject\Choice;
use Phil\SurveyBundle\Domain\ValueObject\Question;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The answers on the edge of what a question reads: padded, half a rank, an identifier made of digits, a grid filled in
 * full.
 */
#[CoversClass(AnswerCheck::class)]
#[CoversClass(Question::class)]
final class AnswersTest extends TestCase
{
    public function testANumberIsReadWithoutItsPadding(): void
    {
        self::assertSame(['value' => 7, 'error' => null], new Question('n', 'N', QuestionType::Number)->accept(' 7 '));
    }

    public function testARankIsAWholeNumberAndNothingElse(): void
    {
        $ranking = new Question('r', 'R', QuestionType::Ranking, choices: [new Choice('a', 'A'), new Choice('b', 'B'), new Choice('c', 'C')]);

        foreach (['1x', 'x1', "1\n", '0', '1000', '1.5'] as $rank) {
            self::assertSame(['value' => null, 'error' => 'type'], $ranking->accept(['a' => $rank]), $rank);
        }

        self::assertSame(['value' => ['a', 'c'], 'error' => null], $ranking->accept(['a' => '1', 'c' => '2']));
        self::assertSame(['value' => null, 'error' => 'type'], $ranking->accept(['a' => '1', 'b' => '1']));
    }

    public function testABlankRankIsSkippedNotTheRestOfTheRanking(): void
    {
        $ranking = new Question('r', 'R', QuestionType::Ranking, choices: [new Choice('a', 'A'), new Choice('b', 'B'), new Choice('c', 'C')]);

        self::assertSame(['value' => ['b', 'c'], 'error' => null], $ranking->accept(['a' => '', 'b' => '1', 'c' => '2']));
        self::assertSame(['value' => ['b'], 'error' => null], $ranking->accept(['a' => null, 'b' => '1']));
    }

    public function testAnOptionMadeOfDigitsKeepsItsText(): void
    {
        $ranking = new Question('r', 'R', QuestionType::Ranking, choices: [new Choice('1', 'One'), new Choice('2', 'Two'), new Choice('3', 'Three')]);

        self::assertSame(['value' => ['2', '1'], 'error' => null], $ranking->accept(['1' => '2', '2' => '1']));
    }

    public function testAGridFilledInFullIsKeptWhenRequired(): void
    {
        $grid = new Question('g', 'G', QuestionType::Matrix, true, choices: [new Choice('a', 'A'), new Choice('b', 'B')], rows: [new Choice('x', 'X'), new Choice('y', 'Y')]);

        self::assertSame(['value' => ['x' => 'a', 'y' => 'b'], 'error' => null], $grid->accept(['x' => 'a', 'y' => 'b']));
        self::assertSame(['value' => null, 'error' => 'required'], $grid->accept(['x' => 'a']));
    }

    public function testABlankRowOfAGridIsSkippedNotTheRestOfTheGrid(): void
    {
        $grid = new Question('g', 'G', QuestionType::Matrix, choices: [new Choice('a', 'A'), new Choice('b', 'B')], rows: [new Choice('x', 'X'), new Choice('y', 'Y')]);

        self::assertSame(['value' => ['y' => 'b'], 'error' => null], $grid->accept(['x' => '', 'y' => 'b']));
        self::assertSame(['value' => ['y' => 'b'], 'error' => null], $grid->accept(['y' => 'b']));
    }

    public function testACountErrorSaysTooManyOrTooFew(): void
    {
        $choices = [new Choice('a', 'A'), new Choice('b', 'B'), new Choice('c', 'C')];
        $pair = new Question('m', 'M', QuestionType::MultipleChoice, choices: $choices, min: 2, max: 2);

        self::assertSame(['value' => null, 'error' => 'too_few'], $pair->accept(['a']));
        self::assertSame(['value' => null, 'error' => 'too_many'], $pair->accept(['a', 'b', 'c']));
        self::assertSame(['value' => ['a', 'b'], 'error' => null], $pair->accept(['a', 'b']));
        self::assertSame(['value' => null, 'error' => 'too_many'], new Question('m', 'M', QuestionType::MultipleChoice, choices: $choices, max: 1)->accept(['a', 'b']));
    }
}
