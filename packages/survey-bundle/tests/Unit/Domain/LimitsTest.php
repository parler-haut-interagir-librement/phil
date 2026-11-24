<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\Poll\BallotKind;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\Model\Read;
use Phil\SurveyBundle\Domain\Status\Anonymity;
use Phil\SurveyBundle\Domain\Status\QuestionType;
use Phil\SurveyBundle\Domain\ValueObject\Choice;
use Phil\SurveyBundle\Domain\ValueObject\Ending;
use Phil\SurveyBundle\Domain\ValueObject\Question;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;
use Phil\SurveyBundle\Domain\ValueObject\Quota;
use Phil\SurveyBundle\Domain\ValueObject\RecordOutcome;
use Phil\SurveyBundle\Domain\ValueObject\Section;
use Phil\SurveyBundle\Tests\Fixtures\Samples;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The edges of every bound, and the exact words of every refusal: a limit one off, a text cut one character short or
 * long, or a message that names the wrong thing, is read here.
 */
#[CoversClass(Read::class)]
#[CoversClass(Choice::class)]
#[CoversClass(Ending::class)]
#[CoversClass(Section::class)]
#[CoversClass(Quota::class)]
#[CoversClass(Question::class)]
#[CoversClass(Questionnaire::class)]
#[CoversClass(RecordOutcome::class)]
final class LimitsTest extends TestCase
{
    /** A text of 200 characters, accents among them, that no rule accepts and no two cuts of it are alike. */
    private static function long(): string
    {
        return str_repeat('éa1b2', 40);
    }

    private function reason(callable $build): string
    {
        try {
            $build();
        } catch (InvalidQuestionnaire $invalidQuestionnaire) {
            return $invalidQuestionnaire->getMessage();
        }

        self::fail('The questionnaire was not refused.');
    }

    private static function question(string $name = 'q'): Question
    {
        return new Question($name, 'Question', QuestionType::Text);
    }

    private static function section(string $name = 'main', int $questions = 1): Section
    {
        $built = [];

        for ($at = 0; $at < $questions; ++$at) {
            $built[] = self::question($questions > 1 ? $name.'_'.$at : 'q_'.$name);
        }

        return new Section($name, 'Section', $built);
    }

    /**
     * @param array{key?: string, sections?: list<Section>, endings?: list<Ending>, quotas?: list<Quota>, computed?: list<array{compute: string, type: string, value: mixed}>, anonymity?: Anonymity, requiresMember?: bool, retentionDays?: int, minimumResponses?: int, opensAt?: DateTimeImmutable, closesAt?: DateTimeImmutable, opensBefore?: int, closesAfter?: int} $overrides
     */
    private static function questionnaire(array $overrides = []): Questionnaire
    {
        return new Questionnaire(
            key: $overrides['key'] ?? 'k',
            title: 'T',
            sections: $overrides['sections'] ?? [self::section()],
            endings: $overrides['endings'] ?? [new Ending('done', 'Done')],
            anonymity: $overrides['anonymity'] ?? Anonymity::Anonymous,
            requiresMember: $overrides['requiresMember'] ?? false,
            computed: $overrides['computed'] ?? [],
            quotas: $overrides['quotas'] ?? [],
            retentionDays: $overrides['retentionDays'] ?? null,
            minimumResponses: $overrides['minimumResponses'] ?? 5,
            opensAt: $overrides['opensAt'] ?? null,
            closesAt: $overrides['closesAt'] ?? null,
            opensBefore: $overrides['opensBefore'] ?? Questionnaire::OPENS_BEFORE,
            closesAfter: $overrides['closesAfter'] ?? Questionnaire::CLOSES_AFTER,
        );
    }

    public function testAMemberNotKnownIsNamedByItsFirstFortyCharacters(): void
    {
        self::assertSame(
            'A thing has no member "'.mb_substr(self::long(), 0, 40).'".',
            $this->reason(static fn (): array => Read::map([self::long() => 1], 'a thing', ['x'])),
        );
    }

    public function testANameIsOneToSixtyFourCharactersFromALetter(): void
    {
        self::assertSame('a', Read::name('a', 'a thing'));
        self::assertSame('a_1', Read::name('a_1', 'a thing'));
        self::assertSame('a'.str_repeat('b', 63), Read::name('a'.str_repeat('b', 63), 'a thing'));

        foreach (['', 'A', '1a', 'a-b', "a\n", 'a b', 'a'.str_repeat('b', 64)] as $name) {
            self::assertStringContainsString('is not.', $this->reason(static fn (): string => Read::name($name, 'a thing')), $name);
        }

        self::assertSame(
            'The name of a thing is 1 to 64 lower-case letters, digits or "_", starting with a letter; "'.mb_substr(self::long(), 0, 80).'" is not.',
            $this->reason(static fn (): string => Read::name(self::long(), 'a thing')),
        );
    }

    public function testATextIsBoundedInCharactersNotBytes(): void
    {
        self::assertSame(str_repeat('é', 5), Read::text(str_repeat('é', 5), 'a thing', 5));
        self::assertSame('x', Read::text('  x  ', 'a thing', 5));
        self::assertSame('', Read::text('  ', 'a thing', 5, true));
        self::assertSame('A thing is 1 to 5 characters.', $this->reason(static fn (): string => Read::text(str_repeat('é', 6), 'a thing', 5)));
        self::assertSame('A thing is 1 to 5 characters.', $this->reason(static fn (): string => Read::text('  ', 'a thing', 5)));
        self::assertSame('A thing is 0 to 5 characters.', $this->reason(static fn (): string => Read::text('abcdef', 'a thing', 5, true)));
        self::assertSame('A thing is 1 to 5 characters.', $this->reason(static fn (): string => Read::text("\xff", 'a thing', 5)));
    }

    public function testAChoiceValueIsAnIdentifier(): void
    {
        self::assertSame('a-B_9', new Choice('a-B_9', 'A')->value);

        foreach (["a\n", 'a b', '', str_repeat('a', 65)] as $value) {
            self::assertStringContainsString('is not.', $this->reason(static fn (): Choice => new Choice($value, 'A')), $value);
        }

        self::assertSame(
            'A choice is 1 to 64 ASCII letters, digits, "_" or "-"; "'.mb_substr(self::long(), 0, 80).'" is not.',
            $this->reason(static fn (): Choice => new Choice(self::long(), 'A')),
        );
        self::assertSame('The label of the choice "a" is 1 to 255 characters.', $this->reason(static fn (): Choice => new Choice('a', '')));
    }

    public function testAnEndingNamesItsFaultsAndKeepsOnlyWhatItHas(): void
    {
        self::assertStringStartsWith('The name of an ending is', $this->reason(static fn (): Ending => new Ending('Done', 'Done')));
        self::assertSame('The title of the ending "done" is 1 to 255 characters.', $this->reason(static fn (): Ending => new Ending('done', '')));
        self::assertSame('The message of the ending "done" is 0 to 5000 characters.', $this->reason(static fn (): Ending => new Ending('done', 'Done', str_repeat('x', Ending::MAX_MESSAGE + 1))));
        self::assertSame('The profile of the ending "done" is 1 to 255 characters.', $this->reason(static fn (): Ending => new Ending('done', 'Done', '', '')));

        $prefix = 'https://example.org/';
        self::assertSame(2048, \strlen(new Ending('done', 'Done', redirect: $prefix.str_repeat('a', 2048 - \strlen($prefix)))->redirect ?? ''));
        self::assertSame('https://example.org/x', new Ending('done', 'Done', redirect: 'https://example.org/x')->redirect);
        self::assertSame('The redirect of the ending "done" is an address of at most 2048 characters.', $this->reason(static fn (): Ending => new Ending('done', 'Done', redirect: $prefix.str_repeat('a', 2049 - \strlen($prefix)))));
        self::assertSame('The redirect of the ending "done" is an address of at most 2048 characters.', $this->reason(static fn (): Ending => new Ending('done', 'Done', redirect: '  ')));

        self::assertSame(['name' => 'done', 'title' => 'Done'], new Ending('done', 'Done')->toArray());
        self::assertSame(
            ['name' => 'done', 'title' => 'Done', 'message' => 'M', 'profile' => 'P', 'redirect' => 'https://example.org/x', 'when' => ['==' => [1, 1]]],
            new Ending('done', 'Done', 'M', 'P', 'https://example.org/x', ['==' => [1, 1]])->toArray(),
        );
    }

    public function testASectionNamesItsFaultsAndKeepsOnlyWhatItHas(): void
    {
        $question = self::question();

        self::assertStringStartsWith('The name of a section is', $this->reason(static fn (): Section => new Section('Main', 'Main', [$question])));
        self::assertSame('The title of the section "main" is 1 to 255 characters.', $this->reason(static fn (): Section => new Section('main', '', [$question])));
        self::assertSame('The description of the section "main" is 0 to '.Section::MAX_DESCRIPTION.' characters.', $this->reason(static fn (): Section => new Section('main', 'Main', [$question], str_repeat('x', Section::MAX_DESCRIPTION + 1))));
        self::assertSame(Section::MAX_DESCRIPTION, mb_strlen(new Section('main', 'Main', [$question], str_repeat('é', Section::MAX_DESCRIPTION))->description));
        self::assertCount(Section::MAX_QUESTIONS, self::section('main', Section::MAX_QUESTIONS)->questions);
        self::assertSame('A section has 1 to '.Section::MAX_QUESTIONS.' questions: "main".', $this->reason(static fn (): Section => self::section('main', Section::MAX_QUESTIONS + 1)));
        self::assertSame('A section has 1 to '.Section::MAX_QUESTIONS.' questions: "main".', $this->reason(static fn (): Section => new Section('main', 'Main', [])));

        self::assertSame(['name' => 'main', 'title' => 'Main', 'questions' => [$question->toArray()]], new Section('main', 'Main', [$question])->toArray());
        $full = new Section('main', 'Main', [$question], 'About', ['==' => [1, 1]]);
        self::assertSame(['name', 'title', 'description', 'shown_when', 'questions'], array_keys($full->toArray()));
        self::assertSame('About', $full->toArray()['description']);

        $read = Section::fromArray(['name' => 'main', 'title' => 'Main', 'description' => 'About', 'questions' => [$question->toArray()]]);
        self::assertSame('About', $read->description);
    }

    public function testAQuotaTakesOneToAMillionResponses(): void
    {
        $when = ['==' => [['var' => 'q'], 'x']];

        self::assertStringStartsWith('The name of a quota is', $this->reason(static fn (): Quota => new Quota('Adults', 1, $when, 'done')));
        self::assertStringStartsWith('The name of the ending of the quota "adults" is', $this->reason(static fn (): Quota => new Quota('adults', 1, $when, 'Done')));
        self::assertSame(1, new Quota('adults', 1, $when, 'done')->limit);
        self::assertSame(Quota::MAX_LIMIT, new Quota('adults', Quota::MAX_LIMIT, $when, 'done')->limit);

        foreach ([0, Quota::MAX_LIMIT + 1] as $limit) {
            self::assertSame('A quota takes 1 to '.Quota::MAX_LIMIT.' responses: "adults".', $this->reason(static fn (): Quota => new Quota('adults', $limit, $when, 'done')), (string) $limit);
        }
    }

    public function testWhatRecordingDidIsSaidByEachOutcome(): void
    {
        $recorded = RecordOutcome::recorded();
        self::assertTrue($recorded->recorded);
        self::assertNull($recorded->fullQuota);
        self::assertFalse($recorded->alreadyAnswered);

        $full = RecordOutcome::quotaFull('adults');
        self::assertFalse($full->recorded);
        self::assertSame('adults', $full->fullQuota);
        self::assertFalse($full->alreadyAnswered);

        $again = RecordOutcome::alreadyAnswered();
        self::assertFalse($again->recorded);
        self::assertNull($again->fullQuota);
        self::assertTrue($again->alreadyAnswered);
    }

    public function testAQuestionIsOptionalAndItsFieldIsChecked(): void
    {
        self::assertFalse(self::question()->required);
        self::assertStringStartsWith('The question "q" is refused: ', $this->reason(static fn (): Question => new Question('q', 'Question', QuestionType::Text, maxLength: 0)));
        self::assertStringStartsWith('The question "q" is refused: ', $this->reason(static fn (): Question => new Question('q', 'Question', QuestionType::MultipleChoice, choices: [new Choice('a', 'A'), new Choice('b', 'B')], max: 5)));
        self::assertSame(2.5, new Question('q', 'Question', QuestionType::Number)->collected(2.5));
    }

    public function testEveryQuestionTypeCountsItsAbstentionAndItsBounds(): void
    {
        $choices = [new Choice('a', 'A'), new Choice('b', 'B'), new Choice('c', 'C')];
        $questions = [
            'single' => new Question('q', 'Q', QuestionType::SingleChoice, choices: $choices),
            'matrix' => new Question('q', 'Q', QuestionType::Matrix, choices: $choices, rows: [new Choice('x', 'X')]),
            'multiple' => new Question('q', 'Q', QuestionType::MultipleChoice, choices: $choices),
            'ranking' => new Question('q', 'Q', QuestionType::Ranking, choices: $choices),
            'scale' => new Question('q', 'Q', QuestionType::Scale, min: 1, max: 5),
            'nps' => new Question('q', 'Q', QuestionType::NetPromoter),
            'yes_no' => new Question('q', 'Q', QuestionType::YesNo),
        ];

        foreach ($questions as $name => $question) {
            self::assertTrue($question->rules()?->abstention, $name);
        }

        $single = $questions['single']->rules();
        $matrix = $questions['matrix']->rules();
        self::assertNotNull($single);
        self::assertNotNull($matrix);
        self::assertSame(BallotKind::Single, $single->kind);
        self::assertSame(['a', 'b', 'c'], $matrix->options);
        self::assertSame(BallotKind::Single, $matrix->kind);
        self::assertSame(1, $questions['multiple']->rules()?->minChoices);
        self::assertSame(1, $questions['ranking']->rules()?->minChoices);
        self::assertSame(2, new Question('q', 'Q', QuestionType::Ranking, choices: $choices, min: 2)->rules()?->minChoices);
        self::assertSame(2, new Question('q', 'Q', QuestionType::MultipleChoice, choices: $choices, min: 2)->rules()?->minChoices);
        self::assertNull(self::question()->rules());
    }

    public function testTheRowsAndTheBoundsOfAQuestionHaveTheirEdges(): void
    {
        $columns = [new Choice('a', 'A'), new Choice('b', 'B')];
        $rows = static fn (int $count): array => array_map(static fn (int $at): Choice => new Choice('r'.$at, 'Row '.$at), range(1, $count));

        self::assertCount(Question::MAX_ROWS, new Question('q', 'Q', QuestionType::Matrix, choices: $columns, rows: $rows(Question::MAX_ROWS))->rows);
        self::assertSame('A matrix has 1 to 50 rows, and only a matrix has rows: "q".', $this->reason(static fn (): Question => new Question('q', 'Q', QuestionType::Matrix, choices: $columns, rows: $rows(Question::MAX_ROWS + 1))));

        self::assertSame(Question::MAX_NUMBER, new Question('q', 'Q', QuestionType::Number, max: Question::MAX_NUMBER)->max);
        self::assertSame(-Question::MAX_NUMBER, new Question('q', 'Q', QuestionType::Number, min: -Question::MAX_NUMBER)->min);

        foreach ([[null, Question::MAX_NUMBER + 1], [-Question::MAX_NUMBER - 1, null]] as [$min, $max]) {
            self::assertSame('A bound of "q" is at most '.Question::MAX_NUMBER.' away from zero.', $this->reason(static fn (): Question => new Question('q', 'Q', QuestionType::Number, min: $min, max: $max)));
        }

        self::assertSame(5, new Question('q', 'Q', QuestionType::Number, min: 5, max: 5)->min);
        self::assertSame('The lowest bound of "q" is not above its highest.', $this->reason(static fn (): Question => new Question('q', 'Q', QuestionType::Number, min: 6, max: 5)));
    }

    public function testAQuestionnaireStartsWithTheSaferChoices(): void
    {
        $questionnaire = new Questionnaire('k', 'T', [self::section()], [new Ending('done', 'Done')]);

        self::assertFalse($questionnaire->requiresMember);
        self::assertFalse($questionnaire->publicResults);
        self::assertSame(5, $questionnaire->minimumResponses);
        self::assertFalse($questionnaire->addenda);
        self::assertSame(15, $questionnaire->opensBefore);
        self::assertSame(4320, $questionnaire->closesAfter);
    }

    public function testAMemberIsNeededWhenAskedOrWhenIdentified(): void
    {
        self::assertFalse(self::questionnaire()->needsMember());
        self::assertTrue(self::questionnaire(['requiresMember' => true])->needsMember());
        self::assertTrue(self::questionnaire(['anonymity' => Anonymity::Identified])->needsMember());
        self::assertTrue(self::questionnaire(['requiresMember' => true, 'anonymity' => Anonymity::Identified])->needsMember());
    }

    public function testTheKeyOfAQuestionnaireIsAnIdentifier(): void
    {
        self::assertSame('a-b_9', self::questionnaire(['key' => 'a-b_9'])->key);
        self::assertSame('9', self::questionnaire(['key' => '9'])->key);
        self::assertSame(str_repeat('a', 64), self::questionnaire(['key' => str_repeat('a', 64)])->key);

        foreach (['', 'a!', "a\n", 'a b', '-a', 'A', str_repeat('a', 65)] as $key) {
            self::assertStringStartsWith('The key of a questionnaire is', $this->reason(static fn (): Questionnaire => self::questionnaire(['key' => $key])), $key);
        }

        self::assertSame(
            'The key of a questionnaire is 1 to 64 lower-case letters, digits, "_" or "-"; "'.mb_substr(self::long(), 0, 80).'" is not.',
            $this->reason(static fn (): Questionnaire => self::questionnaire(['key' => self::long()])),
        );
    }

    public function testAWordNotOfTheListAndAnInstantNotWrittenRightAreQuotedByTheirFirstFortyCharacters(): void
    {
        $cut = mb_substr(self::long(), 0, 40);

        foreach (['kind', 'anonymity', 'pacing'] as $member) {
            $message = $this->reason(static fn (): Questionnaire => Questionnaire::fromArray(Samples::colour([$member => self::long()])));
            self::assertStringStartsWith('"'.$cut.'" is not one of: ', $message, $member);
        }

        self::assertSame(
            'An instant of the window is written as 2026-11-21T19:00:00+00:00; "'.$cut.'" is not.',
            $this->reason(static fn (): Questionnaire => Questionnaire::fromArray(Samples::colour(['window' => ['opens_at' => self::long()]]))),
        );
    }

    public function testTheCountsOfAQuestionnaireHaveTheirEdges(): void
    {
        $sections = static fn (int $count): array => array_map(static fn (int $at): Section => self::section('s'.$at), range(1, $count));
        $endings = static fn (int $count): array => array_map(static fn (int $at): Ending => new Ending('e'.$at, 'End', when: $at < $count ? ['==' => [['var' => 'q_s1'], 'x']] : null), range(1, $count));
        $quotas = static fn (int $count): array => array_map(static fn (int $at): Quota => new Quota('p'.$at, 1, ['==' => [['var' => 'q_s1'], 'x']], 'e1'), range(1, $count));
        $computed = static fn (int $count): array => array_map(static fn (int $at): array => ['compute' => 'c'.$at, 'type' => 'number', 'value' => 1], range(1, $count));
        $sectionsRefused = 'A questionnaire has 1 to 50 sections and at most 200 questions.';
        $endingsRefused = 'A questionnaire has 1 to 50 endings, at most 50 quotas and 50 computed variables.';

        self::assertCount(Questionnaire::MAX_SECTIONS, self::questionnaire(['sections' => $sections(Questionnaire::MAX_SECTIONS)])->sections);
        self::assertSame($sectionsRefused, $this->reason(static fn (): Questionnaire => self::questionnaire(['sections' => $sections(Questionnaire::MAX_SECTIONS + 1)])));
        self::assertSame($sectionsRefused, $this->reason(static fn (): Questionnaire => self::questionnaire(['sections' => []])));

        $perSection = Section::MAX_QUESTIONS;
        $questions = static fn (int $count): array => array_map(static fn (int $at): Section => self::section('t'.$at, min($perSection, $count - ($at - 1) * $perSection)), range(1, (int) ceil($count / $perSection)));
        self::assertCount(Questionnaire::MAX_QUESTIONS, self::questionnaire(['sections' => $questions(Questionnaire::MAX_QUESTIONS)])->questions());
        self::assertSame($sectionsRefused, $this->reason(static fn (): Questionnaire => self::questionnaire(['sections' => $questions(Questionnaire::MAX_QUESTIONS + 1)])));

        self::assertCount(Questionnaire::MAX_ENDINGS, self::questionnaire(['sections' => [self::section('s1')], 'endings' => $endings(Questionnaire::MAX_ENDINGS)])->endings);
        self::assertSame($endingsRefused, $this->reason(static fn (): Questionnaire => self::questionnaire(['sections' => [self::section('s1')], 'endings' => $endings(Questionnaire::MAX_ENDINGS + 1)])));
        self::assertSame($endingsRefused, $this->reason(static fn (): Questionnaire => self::questionnaire(['endings' => []])));

        $base = ['sections' => [self::section('s1')], 'endings' => $endings(2)];
        self::assertCount(Questionnaire::MAX_QUOTAS, self::questionnaire([...$base, 'quotas' => $quotas(Questionnaire::MAX_QUOTAS)])->quotas);
        self::assertSame($endingsRefused, $this->reason(static fn (): Questionnaire => self::questionnaire([...$base, 'quotas' => $quotas(Questionnaire::MAX_QUOTAS + 1)])));
        self::assertCount(Questionnaire::MAX_COMPUTED, self::questionnaire([...$base, 'computed' => $computed(Questionnaire::MAX_COMPUTED)])->computed);
        self::assertSame($endingsRefused, $this->reason(static fn (): Questionnaire => self::questionnaire([...$base, 'computed' => $computed(Questionnaire::MAX_COMPUTED + 1)])));
    }

    /**
     * @return iterable<string, array{array{retentionDays?: int, minimumResponses?: int, opensBefore?: int, closesAfter?: int}, bool}>
     */
    public static function periods(): iterable
    {
        yield 'retention of a day' => [['retentionDays' => 1], true];
        yield 'retention of none' => [['retentionDays' => 0], false];
        yield 'retention of the most' => [['retentionDays' => Questionnaire::MAX_RETENTION_DAYS], true];
        yield 'retention past the most' => [['retentionDays' => Questionnaire::MAX_RETENTION_DAYS + 1], false];
        yield 'one response before results' => [['minimumResponses' => 1], true];
        yield 'no response before results' => [['minimumResponses' => 0], false];
        yield 'a thousand responses before results' => [['minimumResponses' => 1000], true];
        yield 'past a thousand responses' => [['minimumResponses' => 1001], false];
        yield 'feedback opening with the session' => [['opensBefore' => 0], true];
        yield 'feedback opening before the session' => [['opensBefore' => -1], false];
        yield 'feedback opening thirty days before' => [['opensBefore' => 43_200], true];
        yield 'feedback opening past thirty days before' => [['opensBefore' => 43_201], false];
        yield 'feedback closing with the session' => [['closesAfter' => 0], true];
        yield 'feedback closing before the session' => [['closesAfter' => -1], false];
        yield 'feedback closing ninety days after' => [['closesAfter' => 129_600], true];
        yield 'feedback closing past ninety days after' => [['closesAfter' => 129_601], false];
    }

    /**
     * @param array{retentionDays?: int, minimumResponses?: int, opensBefore?: int, closesAfter?: int} $overrides
     */
    #[DataProvider('periods')]
    public function testThePeriodsAndTheMinimumHaveTheirEdges(array $overrides, bool $accepted): void
    {
        if ($accepted) {
            self::assertInstanceOf(Questionnaire::class, self::questionnaire($overrides));

            return;
        }

        $this->expectException(InvalidQuestionnaire::class);
        self::questionnaire($overrides);
    }

    public function testAWindowClosesAfterItOpens(): void
    {
        $at = new DateTimeImmutable('2026-11-21 12:00:00 UTC');

        self::assertSame('A questionnaire closes after it opens.', $this->reason(static fn (): Questionnaire => self::questionnaire(['opensAt' => $at, 'closesAt' => $at])));
        self::assertInstanceOf(Questionnaire::class, self::questionnaire(['opensAt' => $at, 'closesAt' => $at->modify('+1 second')]));
    }
}
