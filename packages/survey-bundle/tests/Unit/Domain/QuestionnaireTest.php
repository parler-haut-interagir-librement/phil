<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\Logic\QuestionnaireLogic;
use Phil\SurveyBundle\Domain\Model\Read;
use Phil\SurveyBundle\Domain\Status\Anonymity;
use Phil\SurveyBundle\Domain\Status\Pacing;
use Phil\SurveyBundle\Domain\Status\QuestionnaireKind;
use Phil\SurveyBundle\Domain\Status\QuestionType;
use Phil\SurveyBundle\Domain\ValueObject\Choice;
use Phil\SurveyBundle\Domain\ValueObject\Ending;
use Phil\SurveyBundle\Domain\ValueObject\Question;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;
use Phil\SurveyBundle\Domain\ValueObject\Quota;
use Phil\SurveyBundle\Domain\ValueObject\Section;
use Phil\SurveyBundle\Tests\Fixtures\Samples;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Questionnaire::class)]
#[CoversClass(Section::class)]
#[CoversClass(Ending::class)]
#[CoversClass(Quota::class)]
#[CoversClass(Read::class)]
#[CoversClass(QuestionnaireLogic::class)]
#[CoversClass(QuestionnaireKind::class)]
final class QuestionnaireTest extends TestCase
{
    /** The question of Samples::colour(). */
    private const array QUESTION = ['name' => 'colour', 'label' => 'Colour', 'type' => 'single_choice', 'required' => true, 'choices' => [['value' => 'red', 'label' => 'Red'], ['value' => 'blue', 'label' => 'Blue']]];

    public function testTheDefaultsAreSafe(): void
    {
        $questionnaire = Questionnaire::fromArray(Samples::colour());

        self::assertSame(QuestionnaireKind::Survey, $questionnaire->kind);
        self::assertSame(Anonymity::Anonymous, $questionnaire->anonymity);
        self::assertTrue($questionnaire->isAnonymous());
        self::assertFalse($questionnaire->needsMember());
        self::assertSame(Pacing::Section, $questionnaire->pacing);
        self::assertFalse($questionnaire->publicResults);
        self::assertSame(5, $questionnaire->minimumResponses);
        self::assertNull($questionnaire->retentionDays);
        self::assertFalse($questionnaire->addenda);
        self::assertSame(15, $questionnaire->opensBefore);
        self::assertSame(4320, $questionnaire->closesAfter);
    }

    public function testTheDataIsWrittenAndReadBack(): void
    {
        $data = Samples::survey([
            'description' => 'About games.',
            'kind' => 'session_feedback',
            'anonymity' => 'identified',
            'pacing' => 'question',
            'requires_member' => true,
            'quotas' => [['name' => 'adults', 'limit' => 10, 'when' => ['==' => [['var' => 'age'], 'adult']], 'ending' => 'thanks']],
            'retention_days' => 30,
            'results' => ['public' => true, 'minimum' => 3],
            'window' => ['opens_at' => '2026-11-01T00:00:00+00:00', 'closes_at' => '2026-12-01T00:00:00+00:00'],
            'feedback' => ['opens_before' => 10, 'closes_after' => 60],
            'addenda' => true,
        ]);
        $questionnaire = Questionnaire::fromArray($data);
        $written = $questionnaire->toArray();

        self::assertSame($written, Questionnaire::fromArray($written)->toArray());
        self::assertSame('identified', $written['anonymity']);
        self::assertSame(['public' => true, 'minimum' => 3], $written['results']);
        self::assertSame(['opens_before' => 10, 'closes_after' => 60], $written['feedback']);
        self::assertSame(['opens_at' => '2026-11-01T00:00:00+00:00', 'closes_at' => '2026-12-01T00:00:00+00:00'], $written['window']);
        self::assertSame($data['quotas'], $written['quotas']);
        self::assertSame($data['computed'], $written['computed']);
        self::assertTrue($questionnaire->needsMember());
        self::assertSame(['key', 'title', 'kind', 'anonymity', 'pacing', 'requires_member', 'sections', 'endings', 'results', 'addenda'], array_keys(Questionnaire::fromArray(Samples::colour())->toArray()));
    }

    public function testAWindowIsReadInItsZoneAndKeptInUtc(): void
    {
        $questionnaire = Questionnaire::fromArray(Samples::colour(['window' => ['opens_at' => '2026-11-21T09:00:00-05:00']]));

        self::assertSame('2026-11-21T14:00:00+00:00', $questionnaire->opensAt?->format(DateTimeInterface::ATOM));
        self::assertFalse($questionnaire->isOpenAt(new DateTimeImmutable('2026-11-21 13:59:59 UTC')));
        self::assertTrue($questionnaire->isOpenAt(new DateTimeImmutable('2026-11-21 14:00:00 UTC')));

        $closing = Questionnaire::fromArray(Samples::colour(['window' => ['closes_at' => '2026-11-21T14:00:00+00:00']]));
        self::assertTrue($closing->isOpenAt(new DateTimeImmutable('2026-11-21 14:00:00 UTC')));
        self::assertFalse($closing->isOpenAt(new DateTimeImmutable('2026-11-21 14:00:01 UTC')));
    }

    public function testTheQuestionsEndingsAndQuotasAreFoundByName(): void
    {
        $questionnaire = Samples::questionnaire(['quotas' => [['name' => 'north', 'limit' => 1, 'when' => ['==' => [['var' => 'region'], 'north']], 'ending' => 'thanks']]]);

        self::assertCount(14, $questionnaire->questions());
        self::assertSame('Fun', $questionnaire->question('fun')?->label);
        self::assertNull($questionnaire->question('nothing'));
        self::assertSame('Thanks', $questionnaire->ending('thanks')?->title);
        self::assertNull($questionnaire->ending('nothing'));
        self::assertSame(1, $questionnaire->quota('north')?->limit);
        self::assertNull($questionnaire->quota('nothing'));
        self::assertTrue(QuestionnaireKind::SessionFeedback->needsSubject());
        self::assertFalse(QuestionnaireKind::Routing->needsSubject());
    }

    public function testAQuestionnaireIsBuiltInPhp(): void
    {
        $questionnaire = new Questionnaire(
            'php',
            ' Built ',
            [new Section('main', 'Main', [new Question('colour', 'Colour', QuestionType::SingleChoice, choices: [new Choice('red', 'Red'), new Choice('blue', 'Blue')])], ' A section ')],
            [new Ending('red', 'Red', when: ['==' => [['var' => 'colour'], 'red']]), new Ending('other', 'Other', ' Bye ', ' Profile ')],
        );

        self::assertSame('Built', $questionnaire->title);
        self::assertSame('A section', $questionnaire->sections[0]->description);
        self::assertSame('Bye', $questionnaire->endings[1]->message);
        self::assertSame('Profile', $questionnaire->endings[1]->profile);
        self::assertSame('red', $questionnaire->logic->ending(['colour' => 'red'])->name);
        self::assertSame('other', $questionnaire->logic->ending(['colour' => 'blue'])->name);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refused(): iterable
    {
        $question = self::QUESTION;
        $section = ['name' => 'main', 'title' => 'Main', 'questions' => [$question]];

        yield 'a bad key' => [['key' => 'Bad key'], 'The key of a questionnaire'];
        yield 'no title' => [['title' => ''], 'The title of the questionnaire'];
        yield 'a description too long' => [['description' => str_repeat('x', 5001)], 'The description of the questionnaire'];
        yield 'no section' => [['sections' => []], 'has 1 to 50 sections'];
        yield 'too many sections' => [['sections' => array_map(static fn (int $at): array => [...$section, 'name' => 's'.$at, 'questions' => [[...$question, 'name' => 'q'.$at]]], range(1, 51))], 'has 1 to 50 sections'];
        yield 'too many questions' => [['sections' => array_map(static fn (int $at): array => [...$section, 'name' => 's'.$at, 'questions' => array_map(static fn (int $q): array => [...$question, 'name' => 'q'.$at.'_'.$q], range(1, 41))], range(1, 5))], 'at most 200 questions'];
        yield 'a section with no question' => [['sections' => [[...$section, 'questions' => []]]], 'A section has 1 to 100 questions'];
        yield 'a section with too many questions' => [['sections' => [[...$section, 'questions' => array_map(static fn (int $q): array => [...$question, 'name' => 'q'.$q], range(1, 101))]]], 'A section has 1 to 100 questions'];
        yield 'no ending' => [['endings' => []], 'has 1 to 50 endings'];
        yield 'too many endings' => [['endings' => [...array_map(static fn (int $at): array => ['name' => 'e'.$at, 'title' => 'E', 'when' => ['==' => [1, 1]]], range(1, 50)), ['name' => 'last', 'title' => 'Last']]], 'has 1 to 50 endings'];
        yield 'too many quotas' => [['quotas' => array_map(static fn (int $at): array => ['name' => 'q'.$at, 'limit' => 1, 'when' => ['==' => [1, 1]], 'ending' => 'thanks'], range(1, 51))], 'at most 50 quotas'];
        yield 'too many computed variables' => [['computed' => array_map(static fn (int $at): array => ['compute' => 'c'.$at, 'type' => 'number', 'value' => 1], range(1, 51))], '50 computed variables'];
        yield 'a question twice' => [['sections' => [[...$section, 'questions' => [$question, $question]]]], 'The names of the questions and computed variables are each given once; "colour" is not.'];
        yield 'a computed variable named as a question' => [['computed' => [['compute' => 'colour', 'type' => 'number', 'value' => 1]]], 'The names of the questions and computed variables'];
        yield 'a section twice' => [['sections' => [$section, [...$section, 'questions' => [[...$question, 'name' => 'other']]]]], 'The names of the sections'];
        yield 'an ending twice' => [['endings' => [['name' => 'a', 'title' => 'A', 'when' => ['==' => [1, 1]]], ['name' => 'a', 'title' => 'A']]], 'The names of the endings'];
        yield 'a quota twice' => [['quotas' => [['name' => 'q', 'limit' => 1, 'when' => ['==' => [1, 1]], 'ending' => 'thanks'], ['name' => 'q', 'limit' => 1, 'when' => ['==' => [1, 1]], 'ending' => 'thanks']]], 'The names of the quotas'];
        yield 'a quota to an unknown ending' => [['quotas' => [['name' => 'q', 'limit' => 1, 'when' => ['==' => [1, 1]], 'ending' => 'gone']]], 'leads to the ending "gone"'];
        yield 'a quota without a limit' => [['quotas' => [['name' => 'q', 'when' => ['==' => [1, 1]], 'ending' => 'thanks']]], 'A quota has its limit.'];
        yield 'a quota without a condition' => [['quotas' => [['name' => 'q', 'limit' => 1, 'ending' => 'thanks']]], 'A quota has its condition.'];
        yield 'a quota of no place' => [['quotas' => [['name' => 'q', 'limit' => 0, 'when' => ['==' => [1, 1]], 'ending' => 'thanks']]], 'A quota takes 1 to 1000000 responses'];
        yield 'a quota too large' => [['quotas' => [['name' => 'q', 'limit' => 1_000_001, 'when' => ['==' => [1, 1]], 'ending' => 'thanks']]], 'A quota takes 1 to 1000000 responses'];
        yield 'a quota leading to a bad name' => [['quotas' => [['name' => 'q', 'limit' => 1, 'when' => ['==' => [1, 1]], 'ending' => 'Bad']]], 'The name of the ending of the quota "q"'];
        yield 'a last ending with a condition' => [['endings' => [['name' => 'a', 'title' => 'A', 'when' => ['==' => [1, 1]]]]], 'Every ending but the last has a condition'];
        yield 'an ending without a condition before the last' => [['endings' => [['name' => 'a', 'title' => 'A'], ['name' => 'b', 'title' => 'B']]], 'Every ending but the last has a condition'];
        yield 'an ending message too long' => [['endings' => [['name' => 'a', 'title' => 'A', 'message' => str_repeat('x', 5001)]]], 'The message of the ending "a"'];
        yield 'an empty profile' => [['endings' => [['name' => 'a', 'title' => 'A', 'profile' => ' ']]], 'The profile of the ending "a"'];
        yield 'an empty redirect' => [['endings' => [['name' => 'a', 'title' => 'A', 'redirect' => ' ']]], 'The redirect of the ending "a"'];
        yield 'a redirect too long' => [['endings' => [['name' => 'a', 'title' => 'A', 'redirect' => 'https://example.org/'.str_repeat('x', 2030)]]], 'The redirect of the ending "a"'];
        yield 'no retention' => [['retention_days' => 0], 'Responses are kept 1 to 3650 days'];
        yield 'a retention too long' => [['retention_days' => 3651], 'Responses are kept 1 to 3650 days'];
        yield 'no minimum' => [['results' => ['minimum' => 0]], 'A result counts 1 to 1000 responses'];
        yield 'a minimum too high' => [['results' => ['minimum' => 1001]], 'A result counts 1 to 1000 responses'];
        yield 'a window closing as it opens' => [['window' => ['opens_at' => '2026-11-21T00:00:00+00:00', 'closes_at' => '2026-11-21T00:00:00+00:00']], 'A questionnaire closes after it opens.'];
        yield 'a window instant badly written' => [['window' => ['opens_at' => '2026-11-21 00:00']], 'An instant of the window is written as'];
        yield 'a window instant that does not exist' => [['window' => ['opens_at' => '2026-02-30T00:00:00+00:00']], 'An instant of the window is written as'];
        yield 'a feedback opening before it can' => [['feedback' => ['opens_before' => -1]], 'The feedback of a session opens 0 to 30 days'];
        yield 'a feedback opening too early' => [['feedback' => ['opens_before' => 43_201]], 'The feedback of a session opens 0 to 30 days'];
        yield 'a feedback closing before it can' => [['feedback' => ['closes_after' => -1]], 'The feedback of a session opens 0 to 30 days'];
        yield 'a feedback closing too late' => [['feedback' => ['closes_after' => 129_601]], 'The feedback of a session opens 0 to 30 days'];
        yield 'an unknown kind' => [['kind' => 'quiz'], '"quiz" is not one of: survey, session_feedback, post_game_letter, routing.'];
        yield 'an unknown anonymity' => [['anonymity' => 'secret'], 'is not one of: anonymous, identified.'];
        yield 'an unknown pacing' => [['pacing' => 'fast'], 'is not one of: question, section, all.'];
        yield 'an unknown member' => [['owner' => 'me'], 'The questionnaire has no member "owner".'];
        yield 'results that are a list' => [['results' => [true]], 'Its results is an object.'];
        yield 'a computed variable that is a text' => [['computed' => ['score']], 'A computed variable is an object.'];
        yield 'a computed variable without a value' => [['computed' => [['compute' => 'score', 'type' => 'number']]], 'A computed variable has its value.'];
        yield 'a computed variable of a bad type' => [['computed' => [['compute' => 'score', 'type' => 'choice', 'value' => 1]]], 'A condition is refused'];
        yield 'a condition outside the subset' => [['sections' => [[...$section, 'questions' => [$question, [...$question, 'name' => 'other', 'shown_when' => ['regex' => ['a', 'b']]]]]]], 'A condition is refused'];
        yield 'a condition reading a later question' => [['sections' => [[...$section, 'questions' => [[...$question, 'shown_when' => ['==' => [['var' => 'other'], 'red']]], [...$question, 'name' => 'other']]]]], 'The condition of the question "colour" reads "other"'];
        yield 'a condition reading itself' => [['sections' => [[...$section, 'questions' => [[...$question, 'shown_when' => ['==' => [['var' => 'colour'], 'red']]]]]]], 'reads "colour"'];
        yield 'a section condition reading its own question' => [['sections' => [[...$section, 'shown_when' => ['==' => [['var' => 'colour'], 'red']]]]], 'The condition of the section "main" reads "colour"'];
        yield 'a condition reading a computed variable' => [['computed' => [['compute' => 'score', 'type' => 'number', 'value' => 1]], 'sections' => [[...$section, 'questions' => [$question, [...$question, 'name' => 'other', 'shown_when' => ['>' => [['var' => 'score'], 1]]]]]]], 'reads "score"'];
        yield 'a condition reading a ranking' => [['sections' => [[...$section, 'questions' => [['name' => 'order', 'label' => 'Order', 'type' => 'ranking', 'choices' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]], [...$question, 'shown_when' => ['in' => ['a', ['var' => 'order']]]]]]]], 'reads "order"'];
        yield 'a condition of the wrong type' => [['sections' => [[...$section, 'questions' => [$question, [...$question, 'name' => 'other', 'shown_when' => ['==' => [['var' => 'colour'], 'green']]]]]]], 'A condition is refused'];
        yield 'an ending condition of the wrong type' => [['endings' => [['name' => 'a', 'title' => 'A', 'when' => ['==' => [['var' => 'colour'], 'green']]], ['name' => 'b', 'title' => 'B']]], 'A condition is refused'];
        yield 'an ending condition reading no question' => [['endings' => [['name' => 'a', 'title' => 'A', 'when' => ['==' => [['var' => 'nothing'], 'x']]], ['name' => 'b', 'title' => 'B']]], 'A condition is refused'];
        yield 'a quota condition of the wrong type' => [['quotas' => [['name' => 'q', 'limit' => 1, 'when' => ['==' => [['var' => 'colour'], 'green']], 'ending' => 'thanks']]], 'A condition is refused'];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('refused')]
    public function testAQuestionnaireOutOfItsRulesIsRefused(array $overrides, string $message): void
    {
        $this->expectException(InvalidQuestionnaire::class);
        $this->expectExceptionMessageIsOrContains($message);

        Questionnaire::fromArray(Samples::colour($overrides));
    }

    public function testAQuestionnaireThatIsNotAnObjectIsRefused(): void
    {
        $this->expectExceptionObject(InvalidQuestionnaire::because('The questionnaire is an object.'));

        Questionnaire::fromArray([1, 2]);
    }

    public function testTheMembersAreCheckedByType(): void
    {
        foreach ([
            [['title' => 3], 'The "title" of the questionnaire is a text.'],
            [['description' => 3], 'The "description" of the questionnaire is a text.'],
            [['addenda' => 'yes'], 'The "addenda" of the questionnaire is true or false.'],
        ] as [$overrides, $message]) {
            try {
                Questionnaire::fromArray(Samples::colour($overrides));
                self::fail('Refused: '.$message);
            } catch (InvalidQuestionnaire $invalidQuestionnaire) {
                self::assertSame($message, $invalidQuestionnaire->getMessage());
            }
        }
    }

    public function testAnEmptyObjectIsAnObject(): void
    {
        self::assertSame([], Read::map([], 'it', []));
        self::assertNull(Read::condition([], 'when', 'it'));
        self::assertSame('a_1', Read::name('a_1', 'it'));
    }
}
