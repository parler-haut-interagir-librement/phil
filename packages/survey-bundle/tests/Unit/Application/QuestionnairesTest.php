<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Unit\Application;

use Override;
use Phil\SurveyBundle\Application\BuiltInTemplates;
use Phil\SurveyBundle\Application\Contract\QuestionnaireTemplates;
use Phil\SurveyBundle\Application\Exception\UnknownQuestionnaire;
use Phil\SurveyBundle\Application\QuestionnaireJson;
use Phil\SurveyBundle\Application\Questionnaires;
use Phil\SurveyBundle\Application\TemplateChain;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\Status\QuestionnaireKind;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;
use Phil\SurveyBundle\Infrastructure\Doctrine\DbalQuestionnaireRepository;
use Phil\SurveyBundle\Infrastructure\Doctrine\Sql;
use Phil\SurveyBundle\Tests\Fixtures\Samples;
use Phil\SurveyBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Questionnaires::class)]
#[CoversClass(QuestionnaireJson::class)]
#[CoversClass(BuiltInTemplates::class)]
#[CoversClass(TemplateChain::class)]
#[CoversClass(UnknownQuestionnaire::class)]
#[CoversClass(DbalQuestionnaireRepository::class)]
#[CoversClass(Sql::class)]
final class QuestionnairesTest extends TestCase
{
    private Stage $stage;

    protected function setUp(): void
    {
        $this->stage = new Stage();
    }

    public function testAQuestionnaireIsSavedReadReplacedAndRemoved(): void
    {
        $this->stage->questionnaires->save(Samples::questionnaire(['retention_days' => 30]));
        $this->stage->questionnaires->save(Questionnaire::fromArray(Samples::colour()));

        self::assertSame(['colour', 'games'], $this->stage->questionnaires->keys());
        self::assertSame(Samples::questionnaire(['retention_days' => 30])->toArray(), $this->stage->questionnaires->get('games')->toArray());
        self::assertSame(['games' => 30], $this->stage->questionnaireRepository->retentions());
        self::assertSame('2026-11-21 19:30:00', $this->stage->connection->fetchOne("SELECT updated_at FROM phil_survey_questionnaires WHERE questionnaire_key = 'games'"));

        $this->stage->clock->modify('+1 hour');
        $this->stage->questionnaires->save(Samples::questionnaire(['title' => 'Games again']));
        self::assertSame('Games again', $this->stage->questionnaires->find('games')?->title);
        self::assertSame([], $this->stage->questionnaireRepository->retentions());
        self::assertSame('2026-11-21 20:30:00', $this->stage->connection->fetchOne("SELECT updated_at FROM phil_survey_questionnaires WHERE questionnaire_key = 'games'"));

        $this->stage->submissions->submit(Samples::questionnaire(['addenda' => true, 'quotas' => [['name' => 'all', 'limit' => 9, 'when' => ['!!' => [['var' => 'age']]], 'ending' => 'thanks']]]), ['age' => 'minor'], 'member:ann');
        $this->stage->connection->insert('phil_survey_addenda', ['response_id' => $this->stage->connection->fetchOne('SELECT id FROM phil_survey_responses'), 'position' => 1, 'body' => 'More', 'added_at' => '2026-11-21 00:00:00']);
        $this->stage->submissions->submit(Questionnaire::fromArray(Samples::colour()), ['colour' => 'red'], 'member:ann');

        self::assertTrue($this->stage->questionnaires->remove('games'));
        self::assertFalse($this->stage->questionnaires->remove('games'));
        self::assertNull($this->stage->questionnaires->find('games'));
        self::assertSame(['colour'], $this->stage->questionnaires->keys());

        foreach (['phil_survey_responses' => 1, 'phil_survey_addenda' => 0, 'phil_survey_quota_counts' => 0, 'phil_survey_participations' => 1] as $table => $rows) {
            self::assertSame($rows, $this->stage->rows($table), $table);
        }
    }

    public function testAnUnknownQuestionnaireIsNamed(): void
    {
        $this->expectExceptionObject(UnknownQuestionnaire::withKey('nothing'));

        $this->stage->questionnaires->get('nothing');
    }

    public function testARedirectOffTheAllowedOriginsIsRefusedOnSave(): void
    {
        $this->stage->questionnaires->save(Questionnaire::fromArray(Samples::colour(['endings' => [['name' => 'go', 'title' => 'Go', 'redirect' => 'https://example.org/next']]])));

        $this->expectException(InvalidQuestionnaire::class);
        $this->expectExceptionMessageIsOrContains('is not on an origin the application allows');
        $this->stage->questionnaires->save(Questionnaire::fromArray(Samples::colour(['key' => 'other', 'endings' => [['name' => 'go', 'title' => 'Go', 'redirect' => 'https://evil.example/']]])));
    }

    public function testAQuestionnaireIsExportedAndImported(): void
    {
        $this->stage->questionnaires->save(Samples::questionnaire());
        $json = $this->stage->questionnaires->export('games');

        self::assertStringStartsWith("{\n    \"format\": \"phil-survey/1\",\n", $json);
        self::assertStringEndsWith("}\n", $json);
        self::assertStringContainsString('"title": "Games"', $json);

        try {
            $this->stage->questionnaires->import($json);
            self::fail('A taken key is refused.');
        } catch (InvalidQuestionnaire $invalidQuestionnaire) {
            self::assertSame('A questionnaire has the key "games" already.', $invalidQuestionnaire->getMessage());
        }

        $replaced = $this->stage->questionnaires->import(str_replace('"title": "Games"', '"title": "Jeux é"', $json), true);
        self::assertSame('Jeux é', $replaced->title);
        self::assertSame('Jeux é', $this->stage->questionnaires->get('games')->title);
        self::assertSame('colour', $this->stage->questionnaires->import(QuestionnaireJson::encode(Questionnaire::fromArray(Samples::colour())))->key);
        self::assertStringContainsString('"https://example.org/a"', QuestionnaireJson::encode(Questionnaire::fromArray(Samples::colour(['endings' => [['name' => 'go', 'title' => 'Go', 'redirect' => 'https://example.org/a']]]))));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badDocuments(): iterable
    {
        yield 'not JSON' => ['{', 'A questionnaire is valid JSON, at most 64 levels deep'];
        yield 'too deep' => [str_repeat('[', 70).str_repeat(']', 70), 'A questionnaire is valid JSON, at most 64 levels deep'];
        yield 'too large' => [str_repeat(' ', QuestionnaireJson::MAX_BYTES + 1), 'A questionnaire is at most 1048576 bytes of JSON.'];
        yield 'a list' => ['[1]', 'The document is an object.'];
        yield 'another format' => ['{"format": "other/1", "questionnaire": {}}', 'The document is of the format "phil-survey/1".'];
        yield 'no format' => ['{"questionnaire": {}}', 'The document is of the format "phil-survey/1".'];
        yield 'another member' => ['{"format": "phil-survey/1", "extra": 1}', 'The document has no member "extra".'];
        yield 'no questionnaire' => ['{"format": "phil-survey/1"}', 'The questionnaire is an object.'];
    }

    #[DataProvider('badDocuments')]
    public function testADocumentOutOfTheFormatIsRefused(string $json, string $message): void
    {
        $this->expectException(InvalidQuestionnaire::class);
        $this->expectExceptionMessageIsOrContains($message);

        QuestionnaireJson::decode($json);
    }

    public function testAQuestionnaireIsMadeFromATemplate(): void
    {
        self::assertSame(['session_feedback' => 'Session feedback'], $this->stage->questionnaires->templates());

        $made = $this->stage->questionnaires->fromTemplate('session_feedback', 'talk-feedback', 'Talk feedback');
        self::assertSame('talk-feedback', $made->key);
        self::assertSame('Talk feedback', $made->title);
        self::assertSame(QuestionnaireKind::SessionFeedback, $made->kind);
        self::assertTrue($made->isAnonymous());
        self::assertSame(3, $made->minimumResponses);
        self::assertSame('Session feedback', $this->stage->questionnaires->fromTemplate('session_feedback', 'other')->title);
        self::assertSame(['other', 'talk-feedback'], $this->stage->questionnaires->keys());
        self::assertNull(new BuiltInTemplates()->template('nothing'));

        try {
            $this->stage->questionnaires->fromTemplate('session_feedback', 'other');
            self::fail('A taken key is refused.');
        } catch (InvalidQuestionnaire $invalidQuestionnaire) {
            self::assertSame('A questionnaire has the key "other" already.', $invalidQuestionnaire->getMessage());
        }

        $this->expectExceptionObject(UnknownQuestionnaire::template('nothing'));
        $this->stage->questionnaires->fromTemplate('nothing', 'x');
    }

    public function testTheTemplatesOfSeveralProvidersAreOne(): void
    {
        $mine = new class implements QuestionnaireTemplates {
            #[Override]
            public function names(): array
            {
                return ['colour' => 'Colour', 'session_feedback' => 'Mine'];
            }

            #[Override]
            public function template(string $name): ?array
            {
                return 'colour' === $name ? array_diff_key(Samples::colour(), ['key' => true]) : null;
            }
        };
        $chain = new TemplateChain([$mine, new BuiltInTemplates()]);

        self::assertSame(['colour' => 'Colour', 'session_feedback' => 'Mine'], $chain->names());
        self::assertSame('Colour', $chain->template('colour')['title'] ?? null);
        self::assertSame('Session feedback', $chain->template('session_feedback')['title'] ?? null);
        self::assertNull($chain->template('nothing'));
        self::assertSame(['a' => 'A', 'b' => 'B'], new TemplateChain([new class implements QuestionnaireTemplates {
            #[Override]
            public function names(): array
            {
                return ['b' => 'B', 'a' => 'A'];
            }

            #[Override]
            public function template(string $name): ?array
            {
                return null;
            }
        }])->names());
    }

    public function testAStoredDefinitionThatNoLongerReadsIsRefused(): void
    {
        $this->stage->connection->insert('phil_survey_questionnaires', ['questionnaire_key' => 'broken', 'definition' => 'not json', 'updated_at' => '2026-11-21 00:00:00']);

        $this->expectExceptionObject(InvalidQuestionnaire::because('A stored column holds a JSON object.'));
        $this->stage->questionnaires->find('broken');
    }
}
