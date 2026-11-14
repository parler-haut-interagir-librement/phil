<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Domain;

use Phil\OnboardingBundle\Domain\Exception\InvalidAnswer;
use Phil\OnboardingBundle\Domain\Exception\InvalidFlow;
use Phil\OnboardingBundle\Domain\Flow\Choice;
use Phil\OnboardingBundle\Domain\Flow\Completion;
use Phil\OnboardingBundle\Domain\Flow\Question;
use Phil\OnboardingBundle\Domain\Flow\StepDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Question::class)]
#[CoversClass(Choice::class)]
#[CoversClass(InvalidAnswer::class)]
#[CoversClass(StepDefinition::class)]
final class QuestionTest extends TestCase
{
    public function testAChoiceKeepsWhatItGrants(): void
    {
        $choice = new Choice('events', 'topic.events', ['ROLE_EVENTS', 'ROLE_EVENTS', 'team:events'], ['letter.events']);

        self::assertSame(['events', 'topic.events', ['ROLE_EVENTS', 'team:events'], ['letter.events']], [$choice->id, $choice->label, $choice->roles, $choice->subscriptions]);
        self::assertTrue($choice->grantsAnything());
        self::assertTrue(new Choice('a', 'A', subscriptions: ['s'])->grantsAnything());
        self::assertTrue(new Choice('a', 'A', ['r'])->grantsAnything());
        self::assertFalse(new Choice('a', 'A')->grantsAnything());
        self::assertCount(20, new Choice('a', 'A', array_map(static fn (int $i): string => 'R'.$i, range(1, 20)))->roles);
        self::assertSame([str_repeat('r', 100)], new Choice('a', 'A', [str_repeat('r', 100)])->roles);
    }

    public function testAChoiceThatBreaksARuleIsRefused(): void
    {
        foreach ([
            [static fn (): Choice => new Choice('A', 'A'), 'The choice "A" is not made of'],
            [static fn (): Choice => new Choice('a', ''), 'The label of the choice "a" "" is empty'],
            [static fn (): Choice => new Choice('a', 'A', array_map(static fn (int $i): string => 'R'.$i, range(1, 21))), 'The choice "a" grants 21 roles, more than 20.'],
            [static fn (): Choice => new Choice('a', 'A', [], array_map(static fn (int $i): string => 'S'.$i, range(1, 21))), 'The choice "a" grants 21 subscriptions, more than 20.'],
            [static fn (): Choice => new Choice('a', 'A', ['ROLE A']), 'The choice "a" grants "ROLE A", which is not made of up to 100 letters'],
            [static fn (): Choice => new Choice('a', 'A', [], ['-letter']), 'The choice "a" grants "-letter"'],
            [static fn (): Choice => new Choice('a', 'A', [str_repeat('r', 101)]), 'The choice "a" grants "'.str_repeat('r', 101).'", which is not made of up to 100'],
        ] as [$declare, $message]) {
            try {
                $declare();
                self::fail('Accepted: '.$message);
            } catch (InvalidFlow $invalidFlow) {
                self::assertStringStartsWith($message, $invalidFlow->getMessage());
            }
        }
    }

    public function testAQuestionOfOneChoice(): void
    {
        $question = new Question([new Choice('new', 'N', ['ROLE_NEW']), new Choice('old', 'O')]);

        self::assertFalse($question->multiple);
        self::assertSame(['new', 'old'], array_keys($question->choices));
        self::assertTrue($question->grantsAnything());
        self::assertSame(['old'], array_map(static fn (Choice $choice): string => $choice->id, $question->pick(['old'])));
        self::assertFalse(new Question([new Choice('a', 'A'), new Choice('b', 'B')])->grantsAnything());
    }

    public function testSeveralChoicesComeInTheQuestionsOrder(): void
    {
        $question = new Question([new Choice('a', 'A'), new Choice('b', 'B'), new Choice('c', 'C')], true);

        self::assertSame(['a', 'c'], array_map(static fn (Choice $choice): string => $choice->id, $question->pick(['c', 'a'])));
        self::assertCount(3, $question->pick(['a', 'b', 'c']));
    }

    public function testAnAnswerThatBreaksARuleIsRefused(): void
    {
        $one = new Question([new Choice('a', 'A'), new Choice('b', 'B')]);
        $many = new Question([new Choice('a', 'A'), new Choice('b', 'B')], true);

        foreach ([
            [$one, [], 'An answer picks at least one choice.'],
            [$one, ['a', 'b'], 'This question takes one choice.'],
            [$many, ['a', 'a'], 'An answer picks each choice once.'],
            [$many, ['a', 'z'], 'An answer picks among the choices of the question.'],
            [$one, ['z'], 'An answer picks among the choices of the question.'],
        ] as [$question, $picked, $message]) {
            try {
                $question->pick($picked);
                self::fail('Accepted: '.$message);
            } catch (InvalidAnswer $invalidAnswer) {
                self::assertSame($message, $invalidAnswer->getMessage());
            }
        }
    }

    public function testAQuestionThatBreaksARuleIsRefused(): void
    {
        $choices = array_map(static fn (int $i): Choice => new Choice('c'.$i, 'C'), range(1, 21));
        self::assertCount(20, new Question(\array_slice($choices, 0, 20))->choices);

        foreach ([
            [[new Choice('a', 'A')], 'A question offers 2 to 20 choices, not 1.'],
            [$choices, 'A question offers 2 to 20 choices, not 21.'],
            [[new Choice('a', 'A'), new Choice('a', 'B')], 'A question offers the choice "a" twice.'],
        ] as [$list, $message]) {
            try {
                new Question($list);
                self::fail('Accepted: '.$message);
            } catch (InvalidFlow $invalidFlow) {
                self::assertSame($message, $invalidFlow->getMessage());
            }
        }
    }

    public function testAStepHasAQuestionExactlyWhenAnAnswerCompletesIt(): void
    {
        $question = new Question([new Choice('a', 'A'), new Choice('b', 'B')]);

        self::assertSame($question, new StepDefinition('s', 'S', Completion::Question, question: $question)->question);

        foreach ([static fn (): StepDefinition => new StepDefinition('s', 'S', Completion::Question), static fn (): StepDefinition => new StepDefinition('s', 'S', question: $question)] as $declare) {
            try {
                $declare();
                self::fail('Accepted.');
            } catch (InvalidFlow $invalidFlow) {
                self::assertSame('The step "s" has a question exactly when it is done by an answer.', $invalidFlow->getMessage());
            }
        }
    }
}
