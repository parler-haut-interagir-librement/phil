<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Unit\Application\Import;

use Phil\ImportWizard\Plan\Reason;
use Phil\ImportWizard\Profile\Column;
use Phil\ImportWizard\Profile\ImportProfile;
use Phil\SurveyBundle\Application\Exception\InvalidQuestionRow;
use Phil\SurveyBundle\Application\Import\ImportedQuestion;
use Phil\SurveyBundle\Application\Import\QuestionCsvProfile;
use Phil\SurveyBundle\Application\Import\QuestionRowRule;
use Phil\SurveyBundle\Application\Import\QuestionRows;
use Phil\SurveyBundle\Domain\Status\QuestionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuestionRows::class)]
#[CoversClass(ImportedQuestion::class)]
#[CoversClass(QuestionRowRule::class)]
#[CoversClass(QuestionCsvProfile::class)]
#[CoversClass(InvalidQuestionRow::class)]
final class QuestionRowsTest extends TestCase
{
    public function testARowOfEveryCellIsReadAsAQuestion(): void
    {
        $read = QuestionRows::read([
            'question_id' => 'genres',
            'type' => 'multiple_choice',
            'label' => '  Genres  ',
            'required' => true,
            'help' => ' Two at most ',
            'choices' => 'horror = Horror | Fantasy |  | scifi=Science fiction = SF',
            'min' => 1,
            'max' => 2,
            'section' => 'more',
            'section_title' => ' More ',
        ], ['!!' => [['var' => 'age']]]);

        self::assertSame('more', $read->section);
        self::assertSame('More', $read->sectionTitle);
        self::assertSame([
            'name' => 'genres',
            'label' => 'Genres',
            'type' => 'multiple_choice',
            'required' => true,
            'help' => 'Two at most',
            'choices' => [['value' => 'horror', 'label' => 'Horror'], ['value' => '2', 'label' => 'Fantasy'], ['value' => 'scifi', 'label' => 'Science fiction = SF']],
            'min' => 1,
            'max' => 2,
            'shown_when' => ['!!' => [['var' => 'age']]],
        ], $read->question->toArray());
    }

    public function testTheEmptyCellsLeaveTheirDefaults(): void
    {
        $read = QuestionRows::read(['question_id' => 'comment', 'type' => 'textarea', 'label' => 'Comment', 'required' => null, 'help' => null, 'section' => '', 'section_title' => '']);

        self::assertNull($read->section);
        self::assertNull($read->sectionTitle);
        self::assertSame(['name' => 'comment', 'label' => 'Comment', 'type' => 'textarea', 'required' => false], $read->question->toArray());
        self::assertFalse(QuestionRows::read(['question_id' => 'q', 'type' => 'text', 'label' => 'Q', 'required' => false])->question->required);
    }

    public function testAMatrixListsItsColumnsAndItsRows(): void
    {
        $question = QuestionRows::read(['question_id' => 'grid', 'type' => 'matrix', 'label' => 'Grid', 'choices' => 'Bad|Good', 'rows' => 'food=Food|room=Room'])->question;

        self::assertSame(['1', '2'], $question->values());
        self::assertSame(['food', 'room'], $question->rowValues());
        self::assertSame('Room', $question->rows[1]->label);
    }

    /**
     * A choice is `value = label` only when what comes before its first `=` is a value and a label follows.
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function choiceCells(): iterable
    {
        yield 'a label alone' => ['1+1=2', '1', '1+1=2'];
        yield 'a value without a label' => ['x=', '1', 'x='];
        yield 'a value and blanks' => ['x =   ', '1', 'x ='];
        yield 'an equal sign first' => ['=Yes', '1', '=Yes'];
        yield 'a value with a dash' => ['a-b_1 = Yes', 'a-b_1', 'Yes'];
        yield 'a value of 64 characters' => [str_repeat('v', 64).'=Yes', str_repeat('v', 64), 'Yes'];
        yield 'a value of 65 characters' => [str_repeat('v', 65).'=Yes', '1', str_repeat('v', 65).'=Yes'];
        yield 'a space inside the value' => ['a b=Yes', '1', 'a b=Yes'];
        yield 'a line break in the label' => ["a=Yes\nor no", 'a', "Yes\nor no"];
    }

    #[DataProvider('choiceCells')]
    public function testAChoiceIsReadAsAValueAndALabelOrAsALabel(string $cell, string $value, string $label): void
    {
        $question = QuestionRows::read(['question_id' => 'q', 'type' => 'single_choice', 'label' => 'Q', 'choices' => $cell.'|other=Other'])->question;

        self::assertSame($value, $question->choices[0]->value);
        self::assertSame($label, $question->choices[0]->label);
    }

    public function testAChoiceLabelOfTheLongestLengthIsRead(): void
    {
        $question = QuestionRows::read(['question_id' => 'q', 'type' => 'single_choice', 'label' => 'Q', 'choices' => ' '.str_repeat('é', 255).' |b=B'])->question;

        self::assertSame(str_repeat('é', 255), $question->choices[0]->label);
    }

    public function testAHundredChoicesAndFiftyRowsAreRead(): void
    {
        $question = QuestionRows::read([
            'question_id' => 'q',
            'type' => 'matrix',
            'label' => 'Q',
            'choices' => implode('|', array_map(static fn (int $n): string => 'C'.$n, range(1, 100))),
            'rows' => implode('|', array_map(static fn (int $n): string => 'R'.$n, range(1, 50))),
        ])->question;

        self::assertCount(100, $question->choices);
        self::assertCount(50, $question->rows);
    }

    public function testEveryTypeIsRead(): void
    {
        $cells = [
            'single_choice' => ['choices' => 'A|B'],
            'multiple_choice' => ['choices' => 'A|B'],
            'ranking' => ['choices' => 'A|B'],
            'matrix' => ['choices' => 'A|B', 'rows' => 'R'],
            'scale' => ['min' => 1, 'max' => 5],
            'number' => ['min' => -3],
            'text' => ['max_length' => 1],
            'email' => ['max_length' => 10_000],
        ];

        foreach (QuestionType::cases() as $type) {
            $question = QuestionRows::read(['question_id' => 'q', 'type' => $type->value, 'label' => 'Q', ...$cells[$type->value] ?? []])->question;
            self::assertSame($type, $question->type);
        }

        self::assertSame(-3, QuestionRows::read(['question_id' => 'q', 'type' => 'number', 'label' => 'Q', 'min' => -3])->question->min);
        self::assertSame(999_999_999_999_999, QuestionRows::read(['question_id' => 'q', 'type' => 'number', 'label' => 'Q', 'max' => 999_999_999_999_999])->question->max);
        self::assertSame(-999_999_999_999_999, QuestionRows::read(['question_id' => 'q', 'type' => 'number', 'label' => 'Q', 'min' => -999_999_999_999_999])->question->min);
        self::assertSame(5, QuestionRows::read(['question_id' => 'q', 'type' => 'scale', 'label' => 'Q', 'min' => 4, 'max' => 5])->question->max);
    }

    /**
     * @return iterable<string, array{array<string, string|int|bool|null>, ?string, string}>
     */
    public static function refusedRows(): iterable
    {
        $q = ['question_id' => 'q', 'label' => 'Q'];

        yield 'an identifier with a capital' => [['question_id' => 'Q1', 'type' => 'text', 'label' => 'Q'], 'question_id', 'An identifier of a question is 1 to 64 lower-case letters, digits or "_", starting with a letter.'];
        yield 'an identifier from a digit' => [['question_id' => '1q', 'type' => 'text', 'label' => 'Q'], 'question_id', 'An identifier of a question is 1 to 64 lower-case letters, digits or "_", starting with a letter.'];
        yield 'an empty identifier' => [['type' => 'text', 'label' => 'Q'], 'question_id', 'An identifier of a question is 1 to 64 lower-case letters, digits or "_", starting with a letter.'];
        yield 'an unknown type' => [[...$q, 'type' => 'essay'], 'type', 'This is not a type of question.'];
        yield 'a section with a dash' => [[...$q, 'type' => 'text', 'section' => 'part-1'], 'section', 'A name of a section is 1 to 64 lower-case letters, digits or "_", starting with a letter.'];
        yield 'a choice without choices' => [[...$q, 'type' => 'single_choice'], 'choices', 'This type of question lists its choices.'];
        yield 'a choice of separators only' => [[...$q, 'type' => 'ranking', 'choices' => ' | | '], 'choices', 'This type of question lists its choices.'];
        yield 'choices of a text' => [[...$q, 'type' => 'text', 'choices' => 'A|B'], 'choices', 'Only a single or multiple choice, a ranking or a matrix lists choices.'];
        yield 'a matrix without rows' => [[...$q, 'type' => 'matrix', 'choices' => 'A|B'], 'rows', 'A matrix lists its rows.'];
        yield 'rows of a choice' => [[...$q, 'type' => 'single_choice', 'choices' => 'A|B', 'rows' => 'R'], 'rows', 'Only a matrix lists rows.'];
        yield 'one choice' => [[...$q, 'type' => 'single_choice', 'choices' => 'A'], 'choices', ''];
        yield '101 choices' => [[...$q, 'type' => 'single_choice', 'choices' => implode('|', range(1, 101))], 'choices', 'A question lists at most 100 choices.'];
        yield '51 rows' => [[...$q, 'type' => 'matrix', 'choices' => 'A|B', 'rows' => implode('|', range(1, 51))], 'rows', 'A question lists at most 50 rows.'];
        yield 'a value twice' => [[...$q, 'type' => 'single_choice', 'choices' => 'a=A|a=B'], 'choices', 'Each of the choices has its own value.'];
        yield 'a value of a position' => [[...$q, 'type' => 'single_choice', 'choices' => 'A|1=B'], 'choices', 'Each of the choices has its own value.'];
        yield 'a row twice' => [[...$q, 'type' => 'matrix', 'choices' => 'A|B', 'rows' => 'r=R|r=S'], 'rows', 'Each of the rows has its own value.'];
        yield 'a long label' => [[...$q, 'type' => 'single_choice', 'choices' => str_repeat('a', 256).'|B'], 'choices', 'A label of the choices is at most 255 characters.'];
        yield 'a long valued label' => [[...$q, 'type' => 'single_choice', 'choices' => 'a='.str_repeat('a', 256).'|B'], 'choices', 'A label of the choices is at most 255 characters.'];
        yield 'a minimum of a text' => [[...$q, 'type' => 'text', 'min' => 1], 'min', 'Only a number, a scale, a multiple choice or a ranking has bounds.'];
        yield 'a maximum of a date' => [[...$q, 'type' => 'date', 'max' => 1], 'max', 'Only a number, a scale, a multiple choice or a ranking has bounds.'];
        yield 'a scale without minimum' => [[...$q, 'type' => 'scale', 'max' => 5], 'min', 'A scale has its lowest and its highest mark.'];
        yield 'a scale without maximum' => [[...$q, 'type' => 'scale', 'min' => 1], 'max', 'A scale has its lowest and its highest mark.'];
        yield 'a minimum too low' => [[...$q, 'type' => 'number', 'min' => -1_000_000_000_000_000], 'min', 'A bound is at most 999999999999999 away from zero.'];
        yield 'a maximum too high' => [[...$q, 'type' => 'number', 'max' => 1_000_000_000_000_000], 'max', 'A bound is at most 999999999999999 away from zero.'];
        yield 'bounds upside down' => [[...$q, 'type' => 'number', 'min' => 5, 'max' => 4], 'max', 'The highest bound is not below the lowest.'];
        yield 'a scale of one mark' => [[...$q, 'type' => 'scale', 'min' => 4, 'max' => 4], 'max', 'The question "q" is refused: A scale has 2 to 100 marks, from its lowest to its highest; not 4 to 4.'];
        yield 'a length of a number' => [[...$q, 'type' => 'number', 'max_length' => 5], 'max_length', 'Only a free text has a length.'];
        yield 'a length of zero' => [[...$q, 'type' => 'text', 'max_length' => 0], 'max_length', 'A free text is 1 to 10000 characters long.'];
        yield 'a length too long' => [[...$q, 'type' => 'textarea', 'max_length' => 10_001], 'max_length', 'A free text is 1 to 10000 characters long.'];
        yield 'a label of blanks' => [[...$q, 'type' => 'text', 'label' => '   '], null, 'The label of "q" is 1 to 255 characters.'];
    }

    /**
     * @param array<string, string|int|bool|null> $values
     */
    #[DataProvider('refusedRows')]
    public function testACellThatBreaksARuleIsNamed(array $values, ?string $column, string $message): void
    {
        try {
            QuestionRows::read($values);
            self::fail('The row is read.');
        } catch (InvalidQuestionRow $invalidQuestionRow) {
            self::assertSame($column, $invalidQuestionRow->column);

            if ('' !== $message) {
                self::assertSame($message, $invalidQuestionRow->getMessage());
            }
        }

        $errors = new QuestionRowRule()->check($values);
        self::assertCount(1, $errors);
        self::assertSame($column, $errors[0]->column);
        self::assertSame(Reason::RefusedByTarget, $errors[0]->reason);
    }

    public function testTheRuleAcceptsAQuestionAndTravelsWithTheProfile(): void
    {
        self::assertSame([], new QuestionRowRule()->check(['question_id' => 'q', 'type' => 'yes_no', 'label' => 'Q']));
        self::assertSame('survey-question', QuestionRowRule::name());
        self::assertSame([], new QuestionRowRule()->toArray());
        self::assertEquals(new QuestionRowRule(), QuestionRowRule::fromArray(['any' => 'thing']));
    }

    public function testTheProfileWritesItsTemplateAndBoundsItsFiles(): void
    {
        $profile = QuestionCsvProfile::profile();

        self::assertSame('survey-question', $profile->type);
        self::assertSame(['question_id'], $profile->naturalKey);
        self::assertSame(
            ['question_id', 'type', 'label', 'required', 'help', 'choices', 'rows', 'min', 'max', 'max_length', 'section', 'section_title'],
            array_map(static fn (Column $column): string => $column->name, $profile->columns),
        );
        self::assertSame(array_column(QuestionType::cases(), 'value'), $profile->column('type')?->choices);
        self::assertSame(32_300, $profile->column('choices')?->maxLength);
        self::assertSame(16_150, $profile->column('rows')?->maxLength);
        self::assertSame(255, $profile->column('label')?->maxLength);
        self::assertSame(1000, $profile->column('help')?->maxLength);
        self::assertSame(64, $profile->column('section')?->maxLength);
        self::assertSame(255, $profile->column('section_title')?->maxLength);
        self::assertSame(64, $profile->column('question_id')?->maxLength);
        self::assertEquals($profile, ImportProfile::fromJson($profile->toJson(), ruleTypes: [QuestionRowRule::class]));

        $limits = QuestionCsvProfile::limits();
        self::assertSame([1_048_576, 300, 32], [$limits->maxBytes, $limits->maxRows, $limits->maxColumns]);
    }
}
