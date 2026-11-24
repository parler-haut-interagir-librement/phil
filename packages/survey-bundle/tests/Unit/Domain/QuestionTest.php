<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Unit\Domain;

use Phil\FormLogic\Typing\VariableType;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\Logic\AnswerCheck;
use Phil\SurveyBundle\Domain\Model\Read;
use Phil\SurveyBundle\Domain\Status\QuestionType;
use Phil\SurveyBundle\Domain\ValueObject\Choice;
use Phil\SurveyBundle\Domain\ValueObject\Question;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Question::class)]
#[CoversClass(AnswerCheck::class)]
#[CoversClass(Choice::class)]
#[CoversClass(Read::class)]
#[CoversClass(QuestionType::class)]
final class QuestionTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, mixed, mixed, ?string}>
     */
    public static function answers(): iterable
    {
        $choices = [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B'], ['value' => 'c', 'label' => 'C']];

        yield 'a text, trimmed' => [['type' => 'text'], '  hello ', 'hello', null];
        yield 'a text too long' => [['type' => 'text', 'max_length' => 3], 'hello', null, 'too_long'];
        yield 'a text not given' => [['type' => 'text', 'required' => true], '', null, 'required'];
        yield 'a text left blank' => [['type' => 'text'], null, null, null];
        yield 'a text that is a list' => [['type' => 'text'], ['x'], null, 'type'];
        yield 'a number written as a whole number' => [['type' => 'textarea'], 12, '12', null];
        yield 'an email' => [['type' => 'email'], 'ann@example.org', 'ann@example.org', null];
        yield 'not an email' => [['type' => 'email'], 'ann', null, 'email'];
        yield 'a date' => [['type' => 'date'], '2026-11-21', '2026-11-21', null];
        yield 'not a date' => [['type' => 'date'], '2026-02-30', null, 'date'];
        yield 'a yes' => [['type' => 'yes_no'], '1', true, null];
        yield 'a no' => [['type' => 'yes_no'], null, false, null];
        yield 'a no where a yes is required' => [['type' => 'yes_no', 'required' => true], null, false, 'required'];
        yield 'a whole number' => [['type' => 'number'], '7', 7, null];
        yield 'a whole number given as one' => [['type' => 'number'], 7, 7, null];
        yield 'a decimal number' => [['type' => 'number'], '-2.5', -2.5, null];
        yield 'a number not a number' => [['type' => 'number'], '1e3', null, 'type'];
        yield 'a number as a list' => [['type' => 'number'], [1], null, 'type'];
        yield 'a number below its lowest' => [['type' => 'number', 'min' => 1], '0', null, 'range'];
        yield 'a number at its lowest' => [['type' => 'number', 'min' => 1], '1', 1, null];
        yield 'a number above its highest' => [['type' => 'number', 'max' => 10], '10.5', null, 'range'];
        yield 'a number at its highest' => [['type' => 'number', 'max' => 10], '10', 10, null];
        yield 'a number required' => [['type' => 'number', 'required' => true], '', null, 'required'];
        yield 'a number left blank' => [['type' => 'number'], null, null, null];
        yield 'an option' => [['type' => 'single_choice', 'choices' => $choices], 'b', 'b', null];
        yield 'not an option' => [['type' => 'single_choice', 'choices' => $choices], 'z', null, 'choice'];
        yield 'an option as a list' => [['type' => 'single_choice', 'choices' => $choices], ['a'], null, 'type'];
        yield 'an option required' => [['type' => 'single_choice', 'choices' => $choices, 'required' => true], null, null, 'required'];
        yield 'a mark' => [['type' => 'scale', 'min' => 1, 'max' => 5], '4', 4, null];
        yield 'a mark given as a number' => [['type' => 'scale', 'min' => 1, 'max' => 5], 4, 4, null];
        yield 'a mark out of the scale' => [['type' => 'scale', 'min' => 1, 'max' => 5], '6', null, 'range'];
        yield 'a promoter' => [['type' => 'net_promoter'], '10', 10, null];
        yield 'several options' => [['type' => 'multiple_choice', 'choices' => $choices], ['c', 'a'], ['c', 'a'], null];
        yield 'no option' => [['type' => 'multiple_choice', 'choices' => $choices], null, null, null];
        yield 'no option of a required question' => [['type' => 'multiple_choice', 'choices' => $choices, 'required' => true], [], null, 'required'];
        yield 'options as a text' => [['type' => 'multiple_choice', 'choices' => $choices], 'a', null, 'type'];
        yield 'options as a map' => [['type' => 'multiple_choice', 'choices' => $choices], ['x' => 'a'], null, 'type'];
        yield 'too many options' => [['type' => 'multiple_choice', 'choices' => $choices, 'max' => 2], ['a', 'b', 'c'], null, 'too_many'];
        yield 'too few options' => [['type' => 'multiple_choice', 'choices' => $choices, 'min' => 2], ['a'], null, 'too_few'];
        yield 'an option twice' => [['type' => 'multiple_choice', 'choices' => $choices], ['a', 'a'], null, 'choice'];
        yield 'an unknown option' => [['type' => 'multiple_choice', 'choices' => $choices], ['a', 'z'], null, 'choice'];
        yield 'an option not a text' => [['type' => 'multiple_choice', 'choices' => $choices], ['a', 1], null, 'choice'];
        yield 'a ranking in order' => [['type' => 'ranking', 'choices' => $choices], ['b', 'a'], ['b', 'a'], null];
        yield 'a ranking by rank' => [['type' => 'ranking', 'choices' => $choices], ['a' => '2', 'b' => '', 'c' => '1'], ['c', 'a'], null];
        yield 'a ranking with a rank twice' => [['type' => 'ranking', 'choices' => $choices], ['a' => '1', 'c' => '1'], null, 'type'];
        yield 'a ranking with a rank that is not one' => [['type' => 'ranking', 'choices' => $choices], ['a' => '0'], null, 'type'];
        yield 'a ranking left blank' => [['type' => 'ranking', 'choices' => $choices], ['a' => '', 'b' => null], null, null];
        yield 'a ranking not given' => [['type' => 'ranking', 'choices' => $choices], '', null, null];
        yield 'a ranking of an unknown option' => [['type' => 'ranking', 'choices' => $choices], ['z' => '1'], null, 'choice'];
        yield 'a ranking as a text' => [['type' => 'ranking', 'choices' => $choices], 'a', null, 'type'];
        yield 'a matrix' => [['type' => 'matrix', 'choices' => $choices, 'rows' => [['value' => 'x', 'label' => 'X'], ['value' => 'y', 'label' => 'Y']]], ['x' => 'a', 'y' => ''], ['x' => 'a'], null];
        yield 'a matrix left blank' => [['type' => 'matrix', 'choices' => $choices, 'rows' => [['value' => 'x', 'label' => 'X']]], null, null, null];
        yield 'a matrix required, a row blank' => [['type' => 'matrix', 'required' => true, 'choices' => $choices, 'rows' => [['value' => 'x', 'label' => 'X'], ['value' => 'y', 'label' => 'Y']]], ['x' => 'a'], null, 'required'];
        yield 'a matrix with an unknown column' => [['type' => 'matrix', 'choices' => $choices, 'rows' => [['value' => 'x', 'label' => 'X']]], ['x' => 'z'], null, 'choice'];
        yield 'a matrix with an unknown row' => [['type' => 'matrix', 'choices' => $choices, 'rows' => [['value' => 'x', 'label' => 'X']]], ['x' => 'a', 'w' => 'a'], null, 'choice'];
        yield 'a matrix as a text' => [['type' => 'matrix', 'choices' => $choices, 'rows' => [['value' => 'x', 'label' => 'X']]], 'a', null, 'type'];
    }

    /**
     * @param array<string, mixed> $question
     */
    #[DataProvider('answers')]
    public function testAnAnswerIsCheckedByItsType(array $question, mixed $submitted, mixed $value, ?string $error): void
    {
        $built = Question::fromArray(['name' => 'q', 'label' => 'Q', ...$question]);

        self::assertSame(['value' => $value, 'error' => $error], $built->accept($submitted));
    }

    public function testTheValueCollectedIsTheOneTheBrowserCollects(): void
    {
        $text = new Question('q', 'Q', QuestionType::Text);
        $yes = new Question('y', 'Y', QuestionType::YesNo);
        $several = new Question('m', 'M', QuestionType::MultipleChoice, choices: [new Choice('a', 'A'), new Choice('b', 'B')]);

        self::assertNull($text->collected('  '));
        self::assertSame('x', $text->collected('x'));
        self::assertSame(3, $text->collected(3));
        self::assertSame(['a' => '1'], $text->collected(['a' => '1']));
        self::assertNull($text->collected(true));
        self::assertTrue($yes->collected('1'));
        self::assertTrue($yes->collected(true));
        self::assertFalse($yes->collected('on'));
        self::assertFalse($yes->collected(null));
        self::assertSame(['a', 'b'], $several->collected(['a', 3, 'b']));
        self::assertSame([], $several->collected('a'));
    }

    public function testTheDataIsWrittenAndReadBack(): void
    {
        $data = ['name' => 'q', 'label' => 'Q', 'type' => 'matrix', 'required' => true, 'help' => 'Help', 'choices' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']], 'rows' => [['value' => 'x', 'label' => 'X']], 'shown_when' => ['==' => [['var' => 'other'], 1]]];

        self::assertSame($data, Question::fromArray($data)->toArray());
        self::assertSame(['name' => 'n', 'label' => 'N', 'type' => 'number', 'required' => false, 'min' => 0, 'max' => 9], Question::fromArray(['name' => 'n', 'label' => ' N ', 'type' => 'number', 'min' => 0, 'max' => 9])->toArray());
        self::assertSame(['name' => 't', 'label' => 'T', 'type' => 'text', 'required' => false], Question::fromArray(['name' => 't', 'label' => 'T', 'type' => 'text'])->toArray());
    }

    public function testTheTypesSayWhatTheyHoldAndWhoReadsThem(): void
    {
        self::assertSame(['single_choice', 'multiple_choice', 'ranking', 'matrix'], array_values(array_map(static fn (QuestionType $type): string => $type->value, array_filter(QuestionType::cases(), static fn (QuestionType $type): bool => $type->hasChoices()))));
        self::assertSame(['yes_no', 'single_choice', 'multiple_choice', 'ranking', 'scale', 'net_promoter', 'matrix'], array_values(array_map(static fn (QuestionType $type): string => $type->value, array_filter(QuestionType::cases(), static fn (QuestionType $type): bool => $type->isCounted()))));
        self::assertSame(['ranking', 'matrix'], array_values(array_map(static fn (QuestionType $type): string => $type->value, array_filter(QuestionType::cases(), static fn (QuestionType $type): bool => !$type->isReadByConditions()))));
        self::assertSame(
            ['text' => 'text', 'textarea' => 'text', 'email' => 'text', 'number' => 'number', 'date' => 'date', 'yes_no' => 'boolean', 'single_choice' => 'choice', 'multiple_choice' => 'choices', 'ranking' => 'choices', 'scale' => 'number', 'net_promoter' => 'number', 'matrix' => null],
            array_combine(array_map(static fn (QuestionType $type): string => $type->value, QuestionType::cases()), array_map(static fn (QuestionType $type): ?string => $type->variableType()?->value, QuestionType::cases())),
        );
        self::assertSame(VariableType::Choice, QuestionType::SingleChoice->variableType());
    }

    public function testTheRulesOfAYesOrNoAreAPollOfTwo(): void
    {
        self::assertSame(['yes', 'no'], new Question('y', 'Y', QuestionType::YesNo)->rules()?->options);
        self::assertNull(new Question('t', 'T', QuestionType::Text)->rules());
        self::assertNull(new Question('n', 'N', QuestionType::Number)->field());
        self::assertSame(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10'], new Question('p', 'P', QuestionType::NetPromoter)->rules()?->options);
        self::assertSame(2, new Question('r', 'R', QuestionType::Ranking, choices: [new Choice('a', 'A'), new Choice('b', 'B'), new Choice('c', 'C')], max: 2)->rules()?->maxChoices);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refused(): iterable
    {
        $two = [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']];

        yield 'a bad name' => [['name' => 'Bad'], 'The name of a question'];
        yield 'an empty label' => [['label' => ' '], 'The label of "q"'];
        yield 'a label too long' => [['label' => str_repeat('x', 256)], 'The label of "q"'];
        yield 'a help too long' => [['help' => str_repeat('x', 1001)], 'The help of "q"'];
        yield 'an unknown type' => [['type' => 'video'], 'The type of a question is one of'];
        yield 'a type that is not a text' => [['type' => 3], 'The "type" of a question is a text.'];
        yield 'a choice without choices' => [['type' => 'single_choice'], 'lists choices when it is a choice'];
        yield 'choices on a text' => [['choices' => $two], 'lists choices when it is a choice'];
        yield 'a choice twice' => [['type' => 'single_choice', 'choices' => [...$two, ['value' => 'a', 'label' => 'Again']]], 'each given once'];
        yield 'a single choice' => [['type' => 'single_choice', 'choices' => [['value' => 'a', 'label' => 'A']]], 'A poll has 2 to 100 options'];
        yield 'a matrix without rows' => [['type' => 'matrix', 'choices' => $two], 'A matrix has 1 to 50 rows'];
        yield 'rows on a choice' => [['type' => 'single_choice', 'choices' => $two, 'rows' => $two], 'A matrix has 1 to 50 rows'];
        yield 'too many rows' => [['type' => 'matrix', 'choices' => $two, 'rows' => array_map(static fn (int $row): array => ['value' => 'r'.$row, 'label' => 'R'], range(1, 51))], 'A matrix has 1 to 50 rows'];
        yield 'a row twice' => [['type' => 'matrix', 'choices' => $two, 'rows' => [['value' => 'x', 'label' => 'X'], ['value' => 'x', 'label' => 'X']]], 'each given once'];
        yield 'bounds on a text' => [['min' => 1], 'Only a number, a scale'];
        yield 'a highest bound on a date' => [['type' => 'date', 'max' => 1], 'Only a number, a scale'];
        yield 'a scale without bounds' => [['type' => 'scale', 'min' => 1], 'A scale has its lowest and its highest mark'];
        yield 'a scale too wide' => [['type' => 'scale', 'min' => 0, 'max' => 100], 'A scale has 2 to 100 marks'];
        yield 'a bound too far' => [['type' => 'number', 'min' => -1_000_000_000_000_000], 'at most 999999999999999 away from zero'];
        yield 'a highest bound too far' => [['type' => 'number', 'max' => 1_000_000_000_000_000], 'at most 999999999999999 away from zero'];
        yield 'bounds crossed' => [['type' => 'number', 'min' => 5, 'max' => 4], 'not above its highest'];
        yield 'a length on a choice' => [['type' => 'single_choice', 'choices' => $two, 'max_length' => 5], 'Only a free text has a length'];
        yield 'a multiple choice holding more than its options' => [['type' => 'multiple_choice', 'choices' => $two, 'max' => 3], 'A ballot holds at least 1 choice'];
        yield 'an unknown member' => [['colour' => 'red'], 'A question has no member "colour".'];
        yield 'a condition that is a list' => [['shown_when' => [1, 2]], 'is one operation'];
        yield 'a condition of two operations' => [['shown_when' => ['==' => [1, 1], '!=' => [1, 2]]], 'is one operation'];
        yield 'a condition that is a text' => [['shown_when' => 'yes'], 'is one operation'];
        yield 'a required that is a text' => [['required' => 'yes'], 'is true or false'];
        yield 'a bound that is a text' => [['type' => 'number', 'min' => '1'], 'is a whole number'];
        yield 'choices that are a map' => [['type' => 'single_choice', 'choices' => ['a' => 'A']], 'is a list'];
        yield 'a choice that is a text' => [['type' => 'single_choice', 'choices' => ['a', 'b']], 'A choice is an object.'];
        yield 'a choice with a bad value' => [['type' => 'single_choice', 'choices' => [['value' => 'a b', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]], 'A choice is 1 to 64 ASCII letters'];
        yield 'a choice with an empty label' => [['type' => 'single_choice', 'choices' => [['value' => 'a', 'label' => ''], ['value' => 'b', 'label' => 'B']]], 'The label of the choice "a"'];
        yield 'a label not in UTF-8' => [['label' => "\xC3"], 'The label of "q"'];
    }

    /**
     * @param array<string, mixed> $question
     */
    #[DataProvider('refused')]
    public function testAQuestionOutOfItsRulesIsRefused(array $question, string $message): void
    {
        $this->expectException(InvalidQuestionnaire::class);
        $this->expectExceptionMessageIsOrContains($message);

        Question::fromArray([...['name' => 'q', 'label' => 'Q', 'type' => 'text'], ...$question]);
    }

    public function testADataThatIsNotAnObjectIsRefused(): void
    {
        $this->expectExceptionObject(InvalidQuestionnaire::because('A question is an object.'));

        Question::fromArray('q');
    }

    public function testAnEmptyHelpIsNone(): void
    {
        self::assertSame('', Question::fromArray(['name' => 'q', 'label' => 'Q', 'type' => 'text', 'help' => '  '])->help);
        self::assertSame('A', new Choice('a', ' A ')->label);
    }
}
