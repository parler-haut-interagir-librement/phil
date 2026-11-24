<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Unit\Application;

use Phil\SurveyBundle\Application\Addenda;
use Phil\SurveyBundle\Application\Exception\SurveyRefused;
use Phil\SurveyBundle\Application\Handler\PurgeSurveyResponsesHandler;
use Phil\SurveyBundle\Domain\ValueObject\Addendum;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;
use Phil\SurveyBundle\Infrastructure\Doctrine\DbalResponseRepository;
use Phil\SurveyBundle\Tests\Fixtures\Samples;
use Phil\SurveyBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Addenda::class)]
#[CoversClass(Addendum::class)]
#[CoversClass(PurgeSurveyResponsesHandler::class)]
#[CoversClass(DbalResponseRepository::class)]
final class AddendaAndPurgeTest extends TestCase
{
    private Stage $stage;

    protected function setUp(): void
    {
        $this->stage = new Stage();
    }

    public function testARespondentAddsToTheirResponseByItsToken(): void
    {
        $questionnaire = Questionnaire::fromArray(Samples::colour(['addenda' => true]));
        $receipt = $this->stage->submissions->submit($questionnaire, ['colour' => 'red']);
        $token = (string) $receipt->addendumToken;

        $this->stage->addenda->add($questionnaire, $token, '  One more thing.  ');
        $this->stage->clock->modify('+1 day');
        $this->stage->addenda->add($questionnaire, $token, 'And another.');

        $addenda = $this->stage->responses->addendaOf((string) $receipt->response);
        self::assertSame(['One more thing.', 'And another.'], array_map(static fn (Addendum $addendum): string => $addendum->text, $addenda));
        self::assertSame('2026-11-22 19:30:00', $addenda[1]->addedAt->format('Y-m-d H:i:s'));
        self::assertSame((string) $receipt->response, $addenda[0]->response);
    }

    public function testAResponseTakesTenAddendaAtMost(): void
    {
        $questionnaire = Questionnaire::fromArray(Samples::colour(['addenda' => true]));
        $token = (string) $this->stage->submissions->submit($questionnaire, ['colour' => 'red'])->addendumToken;

        for ($at = 1; $at <= 10; ++$at) {
            $this->stage->addenda->add($questionnaire, $token, 'Addendum '.$at);
        }

        $this->expectExceptionObject(SurveyRefused::unknownReceipt());
        $this->stage->addenda->add($questionnaire, $token, 'One too many');
    }

    public function testAnAddendumIsRefusedOutOfItsBounds(): void
    {
        $taking = Questionnaire::fromArray(Samples::colour(['addenda' => true]));
        $token = (string) $this->stage->submissions->submit($taking, ['colour' => 'red'])->addendumToken;
        $other = Questionnaire::fromArray(Samples::colour(['key' => 'other', 'addenda' => true]));

        foreach ([
            [Questionnaire::fromArray(Samples::colour()), $token, 'Text', SurveyRefused::ADDENDA_CLOSED],
            [$taking, $token, '   ', SurveyRefused::ADDENDA_CLOSED],
            [$taking, $token, str_repeat('x', 5001), SurveyRefused::ADDENDA_CLOSED],
            [$taking, $token, "\xC3", SurveyRefused::ADDENDA_CLOSED],
            [$taking, strtoupper($token), 'Text', SurveyRefused::UNKNOWN_RECEIPT],
            [$taking, str_repeat('0', 64), 'Text', SurveyRefused::UNKNOWN_RECEIPT],
            [$taking, 'short', 'Text', SurveyRefused::UNKNOWN_RECEIPT],
            [$other, $token, 'Text', SurveyRefused::UNKNOWN_RECEIPT],
        ] as [$questionnaire, $given, $text, $reason]) {
            try {
                $this->stage->addenda->add($questionnaire, $given, $text);
                self::fail('Refused: '.$reason);
            } catch (SurveyRefused $surveyRefused) {
                self::assertSame($reason, $surveyRefused->reason);
            }
        }

        $this->stage->addenda->add($taking, $token, str_repeat('é', 5000));
        self::assertSame(1, $this->stage->rows('phil_survey_addenda'));
    }

    public function testThePurgeDeletesWhatEachRetentionLetsGoAndNothingTwice(): void
    {
        $kept = Samples::questionnaire(['key' => 'kept']);
        $month = Questionnaire::fromArray(Samples::colour(['retention_days' => 30, 'addenda' => true]));
        $this->stage->questionnaires->save($kept);
        $this->stage->questionnaires->save($month);

        $this->stage->clock->modify('2026-10-01 12:00:00 UTC');

        $old = $this->stage->submissions->submit($month, ['colour' => 'red']);
        $this->stage->addenda->add($month, (string) $old->addendumToken, 'Old');
        $this->stage->submissions->submit($kept, ['age' => 'minor']);
        $this->stage->clock->modify('2026-10-22 12:00:00 UTC');
        $this->stage->submissions->submit($month, ['colour' => 'blue']);

        $this->stage->clock->modify('2026-11-21 00:00:00 UTC');

        $purge = new PurgeSurveyResponsesHandler($this->stage->questionnaireRepository, $this->stage->responses, $this->stage->clock);

        self::assertSame(1, $purge());
        self::assertSame(0, $purge());
        self::assertSame(1, $this->stage->responses->count('colour'));
        self::assertSame(1, $this->stage->responses->count('kept'));
        self::assertSame(0, $this->stage->rows('phil_survey_addenda'));

        // 30 days after the day the response was sent, to the second: an anonymous response holds the day only.
        $this->stage->clock->modify('2026-11-21 00:00:01 UTC');
        self::assertSame(1, $purge());
    }

    public function testThePurgeGoesBatchByBatch(): void
    {
        $month = Questionnaire::fromArray(Samples::colour(['retention_days' => 1]));
        $this->stage->questionnaires->save($month);
        $rows = [];

        for ($at = 0; $at < PurgeSurveyResponsesHandler::BATCH + 5; ++$at) {
            $rows[] = \sprintf("('r%05d', 'colour', 'thanks', '[]', '[]', '2026-01-01 00:00:00')", $at);
        }

        $this->stage->connection->executeStatement('INSERT INTO phil_survey_responses (id, questionnaire, ending, answers, computed, submitted_at) VALUES '.implode(', ', $rows));

        self::assertSame(PurgeSurveyResponsesHandler::BATCH, $this->stage->responses->purgeBefore('colour', $this->stage->clock->now(), PurgeSurveyResponsesHandler::BATCH));
        self::assertSame(5, new PurgeSurveyResponsesHandler($this->stage->questionnaireRepository, $this->stage->responses, $this->stage->clock)());
        self::assertSame(0, $this->stage->responses->purgeBefore('colour', $this->stage->clock->now(), 10));
    }
}
