<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\Poll\CountingMethods;
use Phil\Poll\Exception\UnknownCountingMethod;
use Phil\SurveyBundle\Application\Addenda;
use Phil\SurveyBundle\Application\BuiltInTemplates;
use Phil\SurveyBundle\Application\Exception\SurveyRefused;
use Phil\SurveyBundle\Application\Exception\UnknownQuestionnaire;
use Phil\SurveyBundle\Application\Handler\PurgeSurveyResponsesHandler;
use Phil\SurveyBundle\Application\QuestionnaireJson;
use Phil\SurveyBundle\Application\RedirectPolicy;
use Phil\SurveyBundle\Application\Results;
use Phil\SurveyBundle\Domain\Contract\QuestionnaireRepository;
use Phil\SurveyBundle\Domain\Contract\ResponseRepository;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;
use Phil\SurveyBundle\Tests\Fixtures\Samples;
use Phil\SurveyBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The edges of the application layer: the words of a refusal cut where they are cut, an origin told from another that
 * spells alike, the size of a document, the last batch of a purge, the token of a receipt.
 */
#[CoversClass(RedirectPolicy::class)]
#[CoversClass(UnknownQuestionnaire::class)]
#[CoversClass(QuestionnaireJson::class)]
#[CoversClass(BuiltInTemplates::class)]
#[CoversClass(Addenda::class)]
#[CoversClass(PurgeSurveyResponsesHandler::class)]
#[CoversClass(Results::class)]
final class EdgesTest extends TestCase
{
    /** A text of 200 characters, accents among them, in which no two cuts are alike. */
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

        self::fail('The address was not refused.');
    }

    public function testAnUnknownKeyOrTemplateIsQuotedByItsFirstEightyCharacters(): void
    {
        self::assertSame('No questionnaire has the key "'.mb_substr(self::long(), 0, 80).'".', UnknownQuestionnaire::withKey(self::long())->getMessage());
        self::assertSame('No template is named "'.mb_substr(self::long(), 0, 80).'".', UnknownQuestionnaire::template(self::long())->getMessage());
    }

    public function testAnAddressRefusedIsQuotedByItsFirstEightyCharacters(): void
    {
        $cut = mb_substr(self::long(), 0, 80);
        $quoted = static fn (string $message, string $before, string $after): ?string => 1 === preg_match('/^'.preg_quote($before, '/').'"(.*)"'.preg_quote($after, '/').'/su', $message, $found) ? $found[1] : null;

        self::assertSame($cut, $quoted($this->reason(static fn (): RedirectPolicy => new RedirectPolicy([self::long()])), 'The allowed origin ', ' is refused: '));

        $withPath = 'https://example.org/'.self::long();
        self::assertSame(
            'An allowed origin is a scheme, a host and a port only, such as https://example.org; "'.mb_substr($withPath, 0, 80).'" is not.',
            $this->reason(static fn (): RedirectPolicy => new RedirectPolicy([$withPath])),
        );

        $policy = new RedirectPolicy(['https://example.org']);
        self::assertSame($cut, $quoted($this->reason(static fn (): string => $policy->check(self::long())), 'The redirect ', ' is refused: '));

        $elsewhere = 'https://evil.example/'.self::long();
        self::assertSame(
            'The redirect "'.mb_substr($elsewhere, 0, 80).'" is not on an origin the application allows.',
            $this->reason(static fn (): string => $policy->check($elsewhere)),
        );
    }

    public function testAnOriginIsToldFromOneThatSpellsAlike(): void
    {
        $secure = new RedirectPolicy(['https://example.org']);
        self::assertFalse($secure->allows('http://sexample.org/'), 'the scheme and the host run together');
        self::assertTrue($secure->allows('https://example.org/'));

        $port = new RedirectPolicy(['http://example.org:8443']);
        self::assertTrue($port->allows('http://example.org:8443/x'));
        self::assertFalse($port->allows('http://example.org:9000/x'), 'another port');
        self::assertFalse($port->allows('http://example.org/x'), 'no port');
        self::assertFalse($port->allows('http://example.org8443/x'), 'the port run into the host');
        self::assertFalse($port->allows('http://example.org8:443/x'), 'the port run into the host, cut elsewhere');
    }

    public function testADocumentOfTheMostBytesIsRead(): void
    {
        $json = QuestionnaireJson::encode(Questionnaire::fromArray(Samples::colour()));
        $padded = rtrim($json).str_repeat(' ', QuestionnaireJson::MAX_BYTES - \strlen(rtrim($json)));

        self::assertSame(QuestionnaireJson::MAX_BYTES, \strlen($padded));
        self::assertSame('colour', QuestionnaireJson::decode($padded)->key);
        self::assertSame(
            'A questionnaire is at most '.QuestionnaireJson::MAX_BYTES.' bytes of JSON.',
            $this->reason(static fn (): Questionnaire => QuestionnaireJson::decode($padded.' ')),
        );
    }

    public function testTheBuiltInTemplateIsTheSessionFeedbackOfTheDocumentation(): void
    {
        $templates = new BuiltInTemplates();

        self::assertSame(['session_feedback' => 'Session feedback'], $templates->names());
        self::assertNull($templates->template('other'));
        self::assertSame([
            'title' => 'Session feedback',
            'description' => 'Your answers are anonymous: nothing links them to you.',
            'kind' => 'session_feedback',
            'anonymity' => 'anonymous',
            'pacing' => 'all',
            'sections' => [[
                'name' => 'feedback',
                'title' => 'Your feedback',
                'questions' => [
                    ['name' => 'session', 'label' => 'How was the session?', 'type' => 'scale', 'required' => true, 'min' => 1, 'max' => 5],
                    ['name' => 'speaker', 'label' => 'How was the speaker?', 'type' => 'scale', 'min' => 1, 'max' => 5],
                    ['name' => 'keep', 'label' => 'What should be kept?', 'type' => 'textarea', 'max_length' => 2000],
                    ['name' => 'change', 'label' => 'What should change?', 'type' => 'textarea', 'max_length' => 2000],
                ],
            ]],
            'endings' => [['name' => 'thanks', 'title' => 'Thank you', 'message' => "Your feedback reaches the speaker with everyone else's."]],
            'results' => ['public' => false, 'minimum' => 3],
            'feedback' => ['opens_before' => 15, 'closes_after' => 4320],
        ], $templates->template('session_feedback'));
    }

    public function testATokenThatMerelyContainsAReceiptIsNotAReceipt(): void
    {
        $stage = new Stage();
        $questionnaire = Questionnaire::fromArray(Samples::colour(['addenda' => true]));
        $receipt = $stage->submissions->submit($questionnaire, ['colour' => 'red']);
        $token = (string) $receipt->addendumToken;
        $digest = static fn (string $text): string => hash('sha256', $text);

        foreach (['x'.$token, $token.'x', $token."\n", strtoupper($token)] as $at => $other) {
            $stage->connection->insert('phil_survey_responses', ['id' => 'other'.$at, 'questionnaire' => 'colour', 'ending' => 'thanks', 'answers' => '{}', 'computed' => '[]', 'addendum_digest' => $digest($other), 'submitted_at' => '2026-11-21 00:00:00']);

            try {
                $stage->addenda->add($questionnaire, $other, 'Hello.');
                self::fail('The token '.json_encode($other).' was taken.');
            } catch (SurveyRefused $refused) {
                self::assertEquals(SurveyRefused::unknownReceipt(), $refused);
            }
        }

        $stage->addenda->add($questionnaire, $token, 'Hello.');
        self::assertCount(1, $stage->responses->addendaOf((string) $receipt->response));
    }

    public function testAPurgeStopsAtTheFirstShortBatch(): void
    {
        $batches = [PurgeSurveyResponsesHandler::BATCH, PurgeSurveyResponsesHandler::BATCH, 5];
        $calls = 0;
        $responses = $this->createMock(ResponseRepository::class);
        $responses->expects(self::exactly(3))->method('purgeBefore')->willReturnCallback(static function () use (&$calls, $batches): int {
            return $batches[$calls++];
        });

        self::assertSame(2005, $this->purge($responses));
    }

    public function testAPurgeTakesAHundredBatchesAtMost(): void
    {
        $responses = $this->createMock(ResponseRepository::class);
        $responses->expects(self::exactly(PurgeSurveyResponsesHandler::BATCHES))->method('purgeBefore')->willReturn(PurgeSurveyResponsesHandler::BATCH);

        self::assertSame(PurgeSurveyResponsesHandler::BATCH * PurgeSurveyResponsesHandler::BATCHES, $this->purge($responses));
    }

    public function testAPurgeOfNothingTakesOneBatchPerQuestionnaire(): void
    {
        $responses = $this->createMock(ResponseRepository::class);
        $responses->expects(self::exactly(2))->method('purgeBefore')->willReturn(0);
        $questionnaires = self::createStub(QuestionnaireRepository::class);
        $questionnaires->method('retentions')->willReturn(['a' => 30, 'b' => 60]);

        self::assertSame(0, new PurgeSurveyResponsesHandler($questionnaires, $responses, new MockClock('2026-11-21 19:30:00 UTC'))());
    }

    public function testTheMethodsOfTheResultsAreTheOnesGiven(): void
    {
        $stage = new Stage();
        $questionnaire = Questionnaire::fromArray(Samples::colour(['results' => ['minimum' => 1]]));
        $stage->submissions->submit($questionnaire, ['colour' => 'red']);

        $this->expectException(UnknownCountingMethod::class);
        new Results($stage->responses, new CountingMethods([]))->of($questionnaire);
    }

    public function testAGridIsShownAtTheMinimumAndTheYesesAreCounted(): void
    {
        $stage = new Stage();
        $questionnaire = Samples::questionnaire(['results' => ['minimum' => 2]]);

        foreach (['1', '1', null] as $again) {
            $stage->submissions->submit($questionnaire, ['age' => 'minor', 'grid' => ['food' => 'good', 'room' => 'bad'], 'again' => $again]);
        }

        $byName = [];

        foreach ($stage->results->of($questionnaire)->questions as $result) {
            $byName[$result->question->name] = $result;
        }

        self::assertFalse($byName['grid']->withheld);
        self::assertSame(3, $byName['grid']->answered);
        self::assertSame(2, $byName['again']->result?->standingOf('yes')?->score);
        self::assertSame(1, $byName['again']->result->standingOf('no')?->score);

        $stage = new Stage();
        $exact = Samples::questionnaire(['results' => ['minimum' => 2]]);
        $stage->submissions->submit($exact, ['age' => 'minor', 'grid' => ['food' => 'good', 'room' => 'bad']]);
        $stage->submissions->submit($exact, ['age' => 'minor', 'grid' => ['food' => 'good', 'room' => 'bad']]);

        foreach ($stage->results->of($exact)->questions as $result) {
            if ('grid' === $result->question->name) {
                self::assertFalse($result->withheld);
                self::assertSame(2, $result->answered);
            }
        }

        $stage = new Stage();
        $short = Samples::questionnaire(['results' => ['minimum' => 3]]);
        $stage->submissions->submit($short, ['age' => 'minor', 'grid' => ['food' => 'good', 'room' => 'bad']]);
        $stage->submissions->submit($short, ['age' => 'minor', 'grid' => ['food' => 'good', 'room' => 'bad']]);
        $stage->submissions->submit($short, ['age' => 'minor', 'grid' => ['food' => 'good']]);

        $grid = null;

        foreach ($stage->results->of($short)->questions as $result) {
            $grid = 'grid' === $result->question->name ? $result : $grid;
        }

        self::assertTrue($grid?->withheld);
        self::assertSame(2, $grid->answered);
    }

    private function purge(ResponseRepository $responses): int
    {
        $questionnaires = self::createStub(QuestionnaireRepository::class);
        $questionnaires->method('retentions')->willReturn(['colour' => 30]);

        return new PurgeSurveyResponsesHandler($questionnaires, $responses, new MockClock(new DateTimeImmutable('2026-11-21 19:30:00 UTC')))();
    }
}
