<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Unit\Application\Import;

use Override;
use Phil\ImportWizard\Exception\MappingIncomplete;
use Phil\ImportWizard\Exception\PlanChanged;
use Phil\ImportWizard\Exception\SourceRefused;
use Phil\ImportWizard\Import\Batch;
use Phil\ImportWizard\Import\Importer;
use Phil\ImportWizard\Plan\NaturalKey;
use Phil\ImportWizard\Plan\PlannedRow;
use Phil\ImportWizard\Plan\Reason;
use Phil\ImportWizard\Plan\RowAction;
use Phil\ImportWizard\Plan\RowRejection;
use Phil\SurveyBundle\Application\Import\ImportedQuestion;
use Phil\SurveyBundle\Application\Import\QuestionCsvProfile;
use Phil\SurveyBundle\Application\Import\QuestionImports;
use Phil\SurveyBundle\Application\Import\QuestionImportTarget;
use Phil\SurveyBundle\Application\Import\QuestionRowRule;
use Phil\SurveyBundle\Application\Import\QuestionRows;
use Phil\SurveyBundle\Application\Questionnaires;
use Phil\SurveyBundle\Application\RedirectPolicy;
use Phil\SurveyBundle\Domain\Contract\QuestionnaireRepository;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\Status\QuestionType;
use Phil\SurveyBundle\Domain\ValueObject\Ending;
use Phil\SurveyBundle\Domain\ValueObject\Question;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;
use Phil\SurveyBundle\Domain\ValueObject\Section;
use Phil\SurveyBundle\Tests\Fixtures\CountingQuestionnaireRepository;
use Phil\SurveyBundle\Tests\Fixtures\Samples;
use Phil\SurveyBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuestionImports::class)]
#[CoversClass(QuestionImportTarget::class)]
#[CoversClass(QuestionRows::class)]
#[CoversClass(QuestionRowRule::class)]
#[CoversClass(QuestionCsvProfile::class)]
#[CoversClass(ImportedQuestion::class)]
final class QuestionImportsTest extends TestCase
{
    private Stage $stage;

    private QuestionImports $imports;

    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->imports = new QuestionImports($this->stage->questionnaires);
    }

    public function testAFileMakesANewQuestionnaire(): void
    {
        $plan = $this->imports->plan($this->imports->read(<<<'CSV'
            question_id,type,label,required,choices,min,max,section,section_title
            overall,scale,How was it?,yes,,1,5,,
            liked,multiple_choice,What did you like?,no,talks=Talks|food=Food|Venue,,,extras,Extras
            again,yes_no,Again?,,,,,,
            CSV), 'feedback');

        self::assertSame([3, 0, 0], [$plan->creates(), $plan->updates(), $plan->refusals()]);
        self::assertNull($this->stage->questionnaires->find('feedback'));

        $result = $this->imports->commit($plan, 'feedback', 'Feedback', $plan->fingerprint);

        self::assertSame([3, 0, 1, []], [$result->created, $result->updated, $result->batches, $result->rejections]);
        $questionnaire = $this->stage->questionnaires->get('feedback');
        self::assertSame('Feedback', $questionnaire->title);
        self::assertSame([['questions', 'Feedback', ['overall']], ['extras', 'Extras', ['liked', 'again']]], $this->layout($questionnaire));
        self::assertSame(['talks', 'food', '3'], $questionnaire->question('liked')?->values());
        self::assertTrue($questionnaire->question('overall')?->required);
        self::assertSame([['name' => 'thanks', 'title' => 'Thank you']], array_map(static fn (Ending $ending): array => $ending->toArray(), $questionnaire->endings));
    }

    public function testANewQuestionnaireWithoutTitleTakesItsKey(): void
    {
        $plan = $this->imports->plan($this->imports->read("question_id,type,label\nq,text,Q\n"), 'untitled');
        $this->imports->commit($plan, 'untitled');

        $questionnaire = $this->stage->questionnaires->get('untitled');
        self::assertSame('untitled', $questionnaire->title);
        self::assertSame([['questions', 'untitled', ['q']]], $this->layout($questionnaire));
    }

    public function testAFileReplacesMovesAndAddsQuestions(): void
    {
        $this->stage->questionnaires->save(Samples::questionnaire());
        $plan = $this->imports->plan($this->imports->read(<<<'CSV'
            question_id,type,label,choices,section,section_title
            comment,textarea,Tell us more,,,Ignored
            email,email,Your email,,more,
            mood,single_choice,Mood,Good|Bad,,
            venue,text,Venue,,place,
            fun,text,Fun,,,
            CSV), 'games');

        self::assertSame([2, 3, 0], [$plan->creates(), $plan->updates(), $plan->refusals()]);
        $result = $this->imports->commit($plan, 'games', 'Not used');

        self::assertSame([2, 2], [$result->created, $result->updated]);
        self::assertCount(1, $result->rejections);
        self::assertSame(6, $result->rejections[0]->line);
        self::assertNull($result->rejections[0]->errors[0]->column);
        self::assertSame(Reason::RefusedByTarget, $result->rejections[0]->errors[0]->reason);

        $games = $this->stage->questionnaires->get('games');
        self::assertSame('Games', $games->title);
        self::assertSame([
            ['about', 'About you', ['age', 'region']],
            ['adults', 'For adults', ['fun', 'rules', 'comment']],
            ['more', 'More', ['genres', 'order', 'grid', 'again', 'players', 'played', 'recommend', 'nickname', 'email', 'mood']],
            ['place', 'place', ['venue']],
        ], $this->layout($games));
        $comment = $games->question('comment');
        self::assertInstanceOf(Question::class, $comment);
        self::assertSame('Tell us more', $comment->label);
        self::assertSame(['<' => [['var' => 'fun'], 3]], $comment->shownWhen);
        self::assertSame('scale', $games->question('fun')?->type->value);
        self::assertEquals(Samples::questionnaire()->endings, $games->endings);
    }

    public function testAQuestionMovedOutOfItsSectionLeavesNoEmptySection(): void
    {
        $this->stage->questionnaires->save(new Questionnaire('pair', 'Pair', [
            new Section('a', 'A', [new Question('x', 'X', QuestionType::Text)]),
            new Section('b', 'B', [new Question('y', 'Y', QuestionType::Text)]),
        ], [new Ending('end', 'End')]));

        $plan = $this->imports->plan($this->imports->read("question_id,type,label,section\nx,text,X again,b\n"), 'pair');
        $this->imports->commit($plan, 'pair');

        self::assertSame([['b', 'B', ['y', 'x']]], $this->layout($this->stage->questionnaires->get('pair')));
    }

    public function testAQuestionOfItsOwnSectionStaysInPlace(): void
    {
        $this->stage->questionnaires->save(Samples::questionnaire());
        $plan = $this->imports->plan($this->imports->read("question_id,type,label,section\nregion,text,Where?,about\n"), 'games');
        $this->imports->commit($plan, 'games');

        self::assertSame(['about', 'About you', ['age', 'region', 'email']], $this->layout($this->stage->questionnaires->get('games'))[0]);
        self::assertSame('Where?', $this->stage->questionnaires->get('games')->question('region')?->label);
    }

    public function testASaveRefusedRefusesTheRowsOfItsBatchAndTheNextBatchStartsFromWhatIsSaved(): void
    {
        $repository = new class($this->stage->questionnaireRepository) implements QuestionnaireRepository {
            public int $saves = 0;

            public function __construct(private readonly QuestionnaireRepository $inner)
            {
            }

            #[Override]
            public function save(Questionnaire $questionnaire): void
            {
                if (1 === ++$this->saves) {
                    throw InvalidQuestionnaire::because('Not now.');
                }

                $this->inner->save($questionnaire);
            }

            #[Override]
            public function find(string $key): ?Questionnaire
            {
                return $this->inner->find($key);
            }

            #[Override]
            public function keys(): array
            {
                return $this->inner->keys();
            }

            #[Override]
            public function retentions(): array
            {
                return $this->inner->retentions();
            }

            #[Override]
            public function remove(string $key): bool
            {
                return $this->inner->remove($key);
            }
        };
        $questionnaires = new Questionnaires($repository, new RedirectPolicy(['https://example.org']));
        $imports = new QuestionImports($questionnaires, importer: new Importer(1));

        $plan = $imports->plan($imports->read("question_id,type,label\nfirst,text,First\nsecond,text,Second\n"), 'twice');
        $result = $imports->commit($plan, 'twice', 'Twice');

        self::assertSame([1, 2], [$result->created, $result->batches]);
        self::assertSame([2], array_map(static fn (RowRejection $rejection): int => $rejection->line, $result->rejections));
        self::assertSame('Row: is refused — Not now.', $plan->describe($result->rejections[0]->errors[0]));
        self::assertSame([['questions', 'Twice', ['second']]], $this->layout($questionnaires->get('twice')));
    }

    public function testARedirectNoLongerAllowedRefusesEveryRow(): void
    {
        $this->stage->questionnaireRepository->save(Samples::questionnaire(['endings' => [['name' => 'away', 'title' => 'Away', 'redirect' => 'https://evil.example/']]]));
        $plan = $this->imports->plan($this->imports->read("question_id,type,label\nregion,text,Where?\nother,text,Other\n"), 'games');
        $result = $this->imports->commit($plan, 'games');

        self::assertSame([2, 3], array_map(static fn (RowRejection $rejection): int => $rejection->line, $result->rejections));
        self::assertStringContainsString('is not on an origin the application allows', $plan->describe($result->rejections[1]->errors[0]));
        self::assertSame('Region', $this->stage->questionnaires->get('games')->question('region')?->label);
        self::assertNull($this->stage->questionnaires->get('games')->question('other'));
    }

    public function testTheTargetAnswersOnlyForItsQuestionnaire(): void
    {
        $this->stage->questionnaires->save(Samples::questionnaire());
        $target = new QuestionImportTarget($this->stage->questionnaires, 'games');
        $keys = [new NaturalKey(['age']), new NaturalKey(['nothing']), new NaturalKey(['fun'])];

        self::assertSame(QuestionCsvProfile::TYPE, $target->type());
        self::assertSame([$keys[0], $keys[2]], $target->existing(QuestionCsvProfile::TYPE, $keys));
        self::assertSame([], $target->existing('person', $keys));
        self::assertSame([], new QuestionImportTarget($this->stage->questionnaires, 'new-one')->existing(QuestionCsvProfile::TYPE, $keys));
    }

    public function testARowTheTargetCannotReadIsRefusedWithItsCell(): void
    {
        $target = new QuestionImportTarget($this->stage->questionnaires, 'fresh', 'Fresh');
        $refused = $target->write(new Batch(QuestionCsvProfile::TYPE, 1, [
            new PlannedRow(2, RowAction::Create, new NaturalKey(['bad']), ['question_id' => 'bad', 'type' => 'scale', 'label' => 'Bad'], [], []),
        ]));

        self::assertSame([2], array_keys($refused));
        self::assertSame('min', $refused[2][0]->column);
        self::assertSame('A scale has its lowest and its highest mark.', $refused[2][0]->detail);
        self::assertNull($this->stage->questionnaires->find('fresh'));
    }

    public function testTheTargetRefusesEveryRowItCannotReadWithoutKeepingOnlyTheFirst(): void
    {
        $target = new QuestionImportTarget($this->stage->questionnaires, 'fresh');
        $refused = $target->write($this->batch([
            [2, ['question_id' => 'a', 'type' => 'scale', 'label' => 'A']],
            [3, ['question_id' => 'b', 'type' => 'scale', 'label' => 'B']],
        ]));

        self::assertSame([2, 3], array_keys($refused));
    }

    public function testTheRowsRefusedWhenReadAndThoseRefusedByTheQuestionnaireAreAllRefused(): void
    {
        $this->stage->questionnaireRepository->save(Samples::questionnaire());
        $target = new QuestionImportTarget($this->stage->questionnaires, 'games');
        $refused = $target->write($this->batch([
            [2, ['question_id' => 'a', 'type' => 'scale', 'label' => 'A']],
            [3, ['question_id' => 'fun', 'type' => 'text', 'label' => 'Fun']],
            [4, ['question_id' => 'fun', 'type' => 'text', 'label' => 'Fun']],
            [5, ['question_id' => 'new_one', 'type' => 'yes_no', 'label' => 'New?']],
        ]));

        self::assertSame([2, 3, 4], array_keys($refused));
        self::assertSame('min', $refused[2][0]->column);
        self::assertNull($refused[3][0]->column);
        self::assertNull($refused[4][0]->column);
        self::assertSame('New?', $this->stage->questionnaires->get('games')->question('new_one')?->label);
    }

    public function testNothingIsSavedWhenTheQuestionnaireRefusesEveryRow(): void
    {
        $repository = $this->countingRepository();
        $repository->inner->save(Samples::questionnaire());
        $target = new QuestionImportTarget(new Questionnaires($repository, new RedirectPolicy(['https://example.org'])), 'games');

        $refused = $target->write($this->batch([
            [2, ['question_id' => 'fun', 'type' => 'text', 'label' => 'Fun']],
            [3, ['question_id' => 'fun', 'type' => 'text', 'label' => 'Fun']],
        ]));

        self::assertSame([2, 3], array_keys($refused));
        self::assertSame(0, $repository->saves);
    }

    public function testTheQuestionsAndTheSectionsKeptAreRenumbered(): void
    {
        $repository = $this->countingRepository();
        $repository->inner->save(Samples::questionnaire());
        $target = new QuestionImportTarget(new Questionnaires($repository, new RedirectPolicy(['https://example.org'])), 'games');

        $target->write($this->batch([[2, ['question_id' => 'rules', 'type' => 'scale', 'label' => 'Rules', 'min' => 1, 'max' => 5, 'section' => 'more']]]));

        self::assertInstanceOf(Questionnaire::class, $repository->last);
        self::assertSame(['fun', 'comment'], array_map(static fn (Question $question): string => $question->name, $repository->last->sections[1]->questions));
        self::assertSame([0, 1], array_keys($repository->last->sections[1]->questions));

        $target->write($this->batch([
            [2, ['question_id' => 'fun', 'type' => 'scale', 'label' => 'Fun', 'min' => 1, 'max' => 5, 'section' => 'more']],
            [3, ['question_id' => 'comment', 'type' => 'textarea', 'label' => 'Comment', 'section' => 'more']],
        ]));

        self::assertSame(['about', 'more'], array_map(static fn (Section $section): string => $section->name, $repository->last->sections));
        self::assertSame([0, 1], array_keys($repository->last->sections));
    }

    public function testAKeyEndingWithALineBreakIsNoKey(): void
    {
        $this->expectException(InvalidQuestionnaire::class);

        new QuestionImportTarget($this->stage->questionnaires, "games\n");
    }

    public function testAKeyThatIsNoKeyIsRefused(): void
    {
        $this->expectException(InvalidQuestionnaire::class);
        $this->expectExceptionMessageIsOrContains('The key of a questionnaire is 1 to 64 lower-case letters, digits, "_" or "-".');

        $this->imports->plan($this->imports->read("question_id,type,label\nq,text,Q\n"), 'Not a key');
    }

    public function testTheLongestKeyIsAKey(): void
    {
        self::assertSame(QuestionCsvProfile::TYPE, new QuestionImportTarget($this->stage->questionnaires, '0'.str_repeat('a', 63))->type());
        $this->expectException(InvalidQuestionnaire::class);
        new QuestionImportTarget($this->stage->questionnaires, '0'.str_repeat('a', 64));
    }

    public function testAFileWithoutTheRequiredHeadersIsRefused(): void
    {
        $this->expectException(MappingIncomplete::class);

        $this->imports->plan($this->imports->read("question_id,type\nq,text\n"), 'games');
    }

    public function testAPlanChangedSinceThePreviewIsNotWritten(): void
    {
        $plan = $this->imports->plan($this->imports->read("question_id,type,label\nq,text,Q\n"), 'games');

        $this->expectException(PlanChanged::class);
        $this->imports->commit($plan, 'games', null, str_repeat('0', 64));
    }

    public function testAFileBeyondTheBoundsIsRefused(): void
    {
        $this->imports->read("question_id,type,label\n".str_repeat("q,text,Q\n", 300));

        $this->expectException(SourceRefused::class);
        $this->imports->read("question_id,type,label\n".str_repeat("q,text,Q\n", 301));
    }

    public function testTheLargestQuestionnaireIsImportedAndTheNextQuestionRefused(): void
    {
        $rows = implode('', array_map(static fn (int $n): string => \sprintf("q%d,single_choice,Question %d,A|B|C|D,s%d\n", $n, $n, intdiv($n, 50)), range(1, 200)));
        $plan = $this->imports->plan($this->imports->read("question_id,type,label,choices,section\n".$rows), 'large');
        $result = $this->imports->commit($plan, 'large', 'Large');

        self::assertSame([200, []], [$result->created, $result->rejections]);
        self::assertCount(200, $this->stage->questionnaires->get('large')->questions());

        $plan = $this->imports->plan($this->imports->read("question_id,type,label\nq1,text,First again\nq201,text,One more\n"), 'large');
        $result = $this->imports->commit($plan, 'large');

        self::assertSame([0, 1], [$result->created, $result->updated]);
        self::assertSame([3], array_map(static fn (RowRejection $rejection): int => $rejection->line, $result->rejections));
        self::assertStringContainsString('at most 200 questions', $plan->describe($result->rejections[0]->errors[0]));
        self::assertSame('First again', $this->stage->questionnaires->get('large')->question('q1')?->label);
    }

    public function testTheHeadersAreRecognisedInFrench(): void
    {
        $plan = $this->imports->plan($this->imports->read("Identifiant;Type de question;Libellé;Obligatoire;Choix\ncouleur;single_choice;Couleur préférée;oui;Rouge|Bleu\n"), 'couleurs');
        $this->imports->commit($plan, 'couleurs', 'Couleurs');

        $question = $this->stage->questionnaires->get('couleurs')->question('couleur');
        self::assertTrue($question?->required);
        self::assertSame('Bleu', $question->choices[1]->label);
    }

    /**
     * @param non-empty-list<array{int, array<string, string|int>}> $rows
     */
    private function batch(array $rows): Batch
    {
        return new Batch(QuestionCsvProfile::TYPE, 1, array_map(static fn (array $row): PlannedRow => new PlannedRow($row[0], RowAction::Create, new NaturalKey([(string) $row[1]['question_id']]), $row[1], [], []), $rows));
    }

    private function countingRepository(): CountingQuestionnaireRepository
    {
        return new CountingQuestionnaireRepository($this->stage->questionnaireRepository);
    }

    /**
     * @return list<array{string, string, list<string>}>
     */
    private function layout(Questionnaire $questionnaire): array
    {
        return array_map(static fn (Section $section): array => [$section->name, $section->title, array_map(static fn (Question $question): string => $question->name, $section->questions)], $questionnaire->sections);
    }
}
