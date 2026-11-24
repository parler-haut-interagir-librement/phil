<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\SurveyBundle\Application\Contract\FeedbackSessions;
use Phil\SurveyBundle\Application\Exception\SurveyRefused;
use Phil\SurveyBundle\Application\NoSessions;
use Phil\SurveyBundle\Application\Participations;
use Phil\SurveyBundle\Application\Receipt;
use Phil\SurveyBundle\Application\RedirectPolicy;
use Phil\SurveyBundle\Application\Submissions;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\Exception\InvalidResponse;
use Phil\SurveyBundle\Domain\Logic\QuestionnaireLogic;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;
use Phil\SurveyBundle\Domain\ValueObject\RecordOutcome;
use Phil\SurveyBundle\Domain\ValueObject\Response;
use Phil\SurveyBundle\Infrastructure\Doctrine\DbalResponseRepository;
use Phil\SurveyBundle\Tests\Fixtures\Samples;
use Phil\SurveyBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Submissions::class)]
#[CoversClass(Receipt::class)]
#[CoversClass(Participations::class)]
#[CoversClass(SurveyRefused::class)]
#[CoversClass(InvalidResponse::class)]
#[CoversClass(QuestionnaireLogic::class)]
#[CoversClass(RecordOutcome::class)]
#[CoversClass(Response::class)]
#[CoversClass(NoSessions::class)]
final class SubmissionsTest extends TestCase
{
    private Stage $stage;

    protected function setUp(): void
    {
        $this->stage = new Stage();
    }

    public function testTheLogicChoosesTheEndingFromTheAnswersAndTheScore(): void
    {
        $questionnaire = Samples::questionnaire();

        $fan = $this->stage->submissions->submit($questionnaire, ['age' => 'adult', 'fun' => '5', 'rules' => '4']);
        self::assertSame('fan', $fan->ending->name);
        self::assertSame('Enthusiast', $fan->ending->profile);
        self::assertSame(['score' => 9], $fan->computed);
        self::assertTrue($fan->kept);
        self::assertNull($fan->redirect);
        self::assertNull($fan->addendumToken);
        self::assertNull($fan->fullQuota);

        $critic = $this->stage->submissions->submit($questionnaire, ['age' => 'adult', 'fun' => '2', 'grid' => ['food' => 'bad']]);
        self::assertSame('critic', $critic->ending->name);
        self::assertSame(['score' => 2], $critic->computed);

        self::assertSame('thanks', $this->stage->submissions->submit($questionnaire, ['age' => 'minor'])->ending->name);
    }

    public function testTheHiddenQuestionsAreDroppedUnchecked(): void
    {
        $receipt = $this->stage->submissions->submit(Samples::questionnaire(), ['age' => 'minor', 'fun' => 'not a mark', 'rules' => '9', 'comment' => 'hidden']);
        $response = $this->stage->responses->find((string) $receipt->response);

        self::assertNotNull($response);
        self::assertSame(['age' => 'minor', 'again' => false], $response->answers);
        self::assertSame(['score' => 0], $response->computed);
        self::assertSame('thanks', $response->ending);
    }

    public function testTheAnswersAreStoredTyped(): void
    {
        $receipt = $this->stage->submissions->submit(Samples::questionnaire(), [
            'age' => 'adult', 'region' => 'north', 'email' => ' ann@example.org ', 'fun' => '2', 'rules' => '1', 'comment' => 'Too long',
            'genres' => ['fantasy', 'horror'], 'order' => ['a' => '2', 'b' => '1', 'c' => ''], 'grid' => ['food' => 'good', 'room' => 'bad'],
            'again' => '1', 'players' => '4', 'played' => '2026-11-20', 'recommend' => '9', 'nickname' => 'ann',
        ]);

        self::assertSame([
            'age' => 'adult', 'region' => 'north', 'email' => 'ann@example.org', 'fun' => 2, 'rules' => 1, 'comment' => 'Too long',
            'genres' => ['fantasy', 'horror'], 'order' => ['b', 'a'], 'grid' => ['food' => 'good', 'room' => 'bad'],
            'again' => true, 'players' => 4, 'played' => '2026-11-20', 'recommend' => 9, 'nickname' => 'ann',
        ], $this->stage->responses->find((string) $receipt->response)?->answers);
    }

    public function testEveryErrorIsGivenAtOnce(): void
    {
        try {
            $this->stage->submissions->submit(Samples::questionnaire(), ['age' => 'adult', 'fun' => '7', 'email' => 'nope', 'genres' => ['horror', 'fantasy', 'scifi']]);
            self::fail('The answer is refused.');
        } catch (InvalidResponse $invalidResponse) {
            self::assertSame(['email' => 'email', 'fun' => 'range', 'genres' => 'too_many'], $invalidResponse->errors);
            self::assertSame('The answer is refused: email: email, fun: range, genres: too_many.', $invalidResponse->getMessage());
        }

        self::assertSame(0, $this->stage->responses->count('games'));
    }

    public function testAnAnonymousResponseKeepsNothingThatNamesItsRespondent(): void
    {
        $receipt = $this->stage->submissions->submit(Samples::questionnaire(), ['age' => 'minor'], 'member:ann');
        $row = $this->stage->connection->fetchAssociative('SELECT * FROM phil_survey_responses');

        self::assertIsArray($row);
        self::assertNull($row['respondent']);
        self::assertSame('2026-11-21 00:00:00', $row['submitted_at']);
        self::assertIsString($row['id']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $row['id']);
        self::assertSame($receipt->response, $row['id']);
        self::assertStringNotContainsString('ann', implode('|', array_map(static fn (mixed $value): string => \is_scalar($value) ? (string) $value : '', $row)));
        self::assertSame(
            [['questionnaire' => 'games', 'subject' => '', 'respondent_digest' => hash_hmac('sha256', 'phil_survey.participation|member:ann', Stage::SECRET)]],
            $this->stage->connection->fetchAllAssociative('SELECT * FROM phil_survey_participations'),
        );
    }

    public function testAnIdentifiedResponseNamesItsRespondentAndItsInstant(): void
    {
        $questionnaire = Samples::questionnaire(['anonymity' => 'identified']);
        $receipt = $this->stage->submissions->submit($questionnaire, ['age' => 'minor'], 'member:ann');
        $response = $this->stage->responses->find((string) $receipt->response);

        self::assertSame('member:ann', $response?->respondent);
        self::assertSame('2026-11-21 19:30:00 UTC', $response->submittedAt->format('Y-m-d H:i:s e'));
    }

    public function testAMemberAnswersOnce(): void
    {
        $questionnaire = Samples::questionnaire();
        self::assertFalse($this->stage->submissions->hasAnswered($questionnaire, 'member:ann'));
        self::assertFalse($this->stage->submissions->hasAnswered($questionnaire, null));

        $this->stage->submissions->submit($questionnaire, ['age' => 'minor'], 'member:ann');
        self::assertTrue($this->stage->submissions->hasAnswered($questionnaire, 'member:ann'));
        self::assertFalse($this->stage->submissions->hasAnswered($questionnaire, 'member:bob'));

        try {
            $this->stage->submissions->submit($questionnaire, ['age' => 'minor'], 'member:ann');
            self::fail('A second response is refused.');
        } catch (SurveyRefused $surveyRefused) {
            self::assertSame(SurveyRefused::ALREADY_ANSWERED, $surveyRefused->reason);
        }

        self::assertSame(1, $this->stage->responses->count('games'));
        $this->stage->submissions->submit($questionnaire, ['age' => 'minor']);
        $this->stage->submissions->submit($questionnaire, ['age' => 'minor']);
        self::assertSame(3, $this->stage->responses->count('games'));
    }

    public function testAQuestionnaireThatNeedsAMemberRefusesAGuest(): void
    {
        foreach ([Samples::questionnaire(['requires_member' => true]), Samples::questionnaire(['anonymity' => 'identified'])] as $questionnaire) {
            try {
                $this->stage->submissions->submit($questionnaire, ['age' => 'minor']);
                self::fail('A guest is refused.');
            } catch (SurveyRefused $surveyRefused) {
                self::assertSame(SurveyRefused::MEMBER_REQUIRED, $surveyRefused->reason);
            }
        }
    }

    public function testAClosedQuestionnaireRefusesResponses(): void
    {
        $questionnaire = Samples::questionnaire(['window' => ['opens_at' => '2026-11-22T00:00:00+00:00']]);
        self::assertFalse($this->stage->submissions->isOpen($questionnaire));

        $this->expectExceptionObject(SurveyRefused::closed());

        $this->stage->submissions->submit($questionnaire, ['age' => 'minor']);
    }

    public function testASessionFeedbackOpensInTheWindowOfItsSession(): void
    {
        $sessions = new class implements FeedbackSessions {
            #[Override]
            public function session(string $subject): ?array
            {
                return 'session:1' === $subject ? [new DateTimeImmutable('2026-11-21 20:00:00 UTC'), new DateTimeImmutable('2026-11-21 21:00:00 UTC')] : null;
            }
        };
        $stage = new Stage(sessions: $sessions);
        $questionnaire = Questionnaire::fromArray(Samples::colour(['kind' => 'session_feedback', 'feedback' => ['opens_before' => 30, 'closes_after' => 60]]));

        self::assertTrue($stage->submissions->isOpen($questionnaire, 'session:1'));
        self::assertFalse($stage->submissions->isOpen($questionnaire, 'session:2'));
        self::assertFalse($stage->submissions->isOpen($questionnaire));

        $stage->clock->modify('2026-11-21 19:29:59 UTC');
        self::assertFalse($stage->submissions->isOpen($questionnaire, 'session:1'));
        $stage->clock->modify('2026-11-21 22:00:00 UTC');
        self::assertTrue($stage->submissions->isOpen($questionnaire, 'session:1'));
        $receipt = $stage->submissions->submit($questionnaire, ['colour' => 'red'], null, 'session:1');
        self::assertSame('session:1', $stage->responses->find((string) $receipt->response)?->subject);
        self::assertSame(1, $stage->responses->count('colour', 'session:1'));
        self::assertSame(0, $stage->responses->count('colour', 'session:2'));
        $stage->clock->modify('2026-11-21 22:00:01 UTC');
        self::assertFalse($stage->submissions->isOpen($questionnaire, 'session:1'));

        try {
            $stage->submissions->submit($questionnaire, ['colour' => 'red']);
            self::fail('A session feedback needs its subject.');
        } catch (SurveyRefused $surveyRefused) {
            self::assertSame(SurveyRefused::SUBJECT_REQUIRED, $surveyRefused->reason);
        }

        self::assertFalse($this->stage->submissions->isOpen($questionnaire, 'session:1'));
    }

    public function testAMemberAnswersASessionFeedbackOncePerSession(): void
    {
        $sessions = new class implements FeedbackSessions {
            #[Override]
            public function session(string $subject): array
            {
                return [new DateTimeImmutable('2026-11-21 19:00:00 UTC'), new DateTimeImmutable('2026-11-21 20:00:00 UTC')];
            }
        };
        $stage = new Stage(sessions: $sessions);
        $questionnaire = Questionnaire::fromArray(Samples::colour(['kind' => 'session_feedback']));

        $stage->submissions->submit($questionnaire, ['colour' => 'red'], 'member:ann', 'session:1');
        $stage->submissions->submit($questionnaire, ['colour' => 'red'], 'member:ann', 'session:2');

        self::assertTrue($stage->submissions->hasAnswered($questionnaire, 'member:ann', 'session:1'));
        self::assertFalse($stage->submissions->hasAnswered($questionnaire, 'member:ann', 'session:3'));
        $this->expectExceptionObject(SurveyRefused::alreadyAnswered());
        $stage->submissions->submit($questionnaire, ['colour' => 'blue'], 'member:ann', 'session:1');
    }

    public function testAFullQuotaScreensOutAndKeepsNothing(): void
    {
        $questionnaire = Samples::questionnaire([
            'quotas' => [
                ['name' => 'north_adults', 'limit' => 1, 'when' => ['and' => [['==' => [['var' => 'age'], 'adult']], ['==' => [['var' => 'region'], 'north']]]], 'ending' => 'critic'],
                ['name' => 'adults', 'limit' => 2, 'when' => ['==' => [['var' => 'age'], 'adult']], 'ending' => 'thanks'],
            ],
        ]);

        $first = $this->stage->submissions->submit($questionnaire, ['age' => 'adult', 'region' => 'north', 'fun' => '3'], 'member:ann');
        self::assertTrue($first->kept);

        $second = $this->stage->submissions->submit($questionnaire, ['age' => 'adult', 'region' => 'north', 'fun' => '3'], 'member:bob');
        self::assertFalse($second->kept);
        self::assertNull($second->response);
        self::assertSame('north_adults', $second->fullQuota);
        self::assertSame('critic', $second->ending->name);
        self::assertFalse($this->stage->submissions->hasAnswered($questionnaire, 'member:bob'));

        self::assertTrue($this->stage->submissions->submit($questionnaire, ['age' => 'adult', 'region' => 'south', 'fun' => '3'])->kept);
        $third = $this->stage->submissions->submit($questionnaire, ['age' => 'adult', 'region' => 'south', 'fun' => '3']);
        self::assertSame('adults', $third->fullQuota);
        self::assertSame('thanks', $third->ending->name);
        self::assertTrue($this->stage->submissions->submit($questionnaire, ['age' => 'minor'])->kept);

        self::assertSame(['adults' => 2, 'north_adults' => 1], $this->stage->responses->quotaCounts('games'));
        self::assertSame(3, $this->stage->responses->count('games'));
    }

    public function testAQuotaFullBeforeAnotherLeavesTheOtherUntouched(): void
    {
        $questionnaire = Questionnaire::fromArray(Samples::colour([
            'quotas' => [
                ['name' => 'all', 'limit' => 5, 'when' => ['!!' => [['var' => 'colour']]], 'ending' => 'thanks'],
                ['name' => 'red', 'limit' => 1, 'when' => ['==' => [['var' => 'colour'], 'red']], 'ending' => 'thanks'],
            ],
        ]));

        $this->stage->submissions->submit($questionnaire, ['colour' => 'red']);
        $this->stage->submissions->submit($questionnaire, ['colour' => 'red']);

        self::assertSame(['all' => 1, 'red' => 1], $this->stage->responses->quotaCounts('colour'));
    }

    public function testAnEndingRedirectsOnlyToAnAllowedOrigin(): void
    {
        $questionnaire = Questionnaire::fromArray(Samples::colour(['endings' => [
            ['name' => 'red', 'title' => 'Red', 'redirect' => 'https://EXAMPLE.org/book?colour=red', 'when' => ['==' => [['var' => 'colour'], 'red']]],
            ['name' => 'blue', 'title' => 'Blue', 'redirect' => 'https://elsewhere.example/'],
        ]]));

        self::assertSame('https://example.org/book?colour=red', $this->stage->submissions->submit($questionnaire, ['colour' => 'red'])->redirect);
        $blue = $this->stage->submissions->submit($questionnaire, ['colour' => 'blue']);
        self::assertNull($blue->redirect);
        self::assertSame('blue', $blue->ending->name);
    }

    public function testAQuestionnaireThatTakesAddendaGivesATokenAndKeepsItsDigest(): void
    {
        $receipt = $this->stage->submissions->submit(Questionnaire::fromArray(Samples::colour(['addenda' => true])), ['colour' => 'red']);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $receipt->addendumToken);
        self::assertSame(hash('sha256', (string) $receipt->addendumToken), $this->stage->responses->find((string) $receipt->response)?->addendumDigest);
    }

    public function testTheParticipationsNeedALongSecret(): void
    {
        self::assertSame(64, \strlen(new Participations(str_repeat('k', 16))->digest('a')));
        self::assertNotSame(new Participations(str_repeat('k', 16))->digest('a'), new Participations(str_repeat('j', 16))->digest('a'));

        $this->expectExceptionObject(InvalidQuestionnaire::because('The secret of the participations is at least 16 bytes.'));
        new Participations(str_repeat('k', 15));
    }

    public function testTheRefusalsSayWhy(): void
    {
        foreach ([
            SurveyRefused::MEMBER_REQUIRED => SurveyRefused::memberRequired(),
            SurveyRefused::SUBJECT_REQUIRED => SurveyRefused::subjectRequired(),
            SurveyRefused::RESULTS_PRIVATE => SurveyRefused::resultsPrivate(),
            SurveyRefused::ADDENDA_CLOSED => SurveyRefused::addendaClosed(),
            SurveyRefused::UNKNOWN_RECEIPT => SurveyRefused::unknownReceipt(),
            SurveyRefused::UNTRUSTED_REQUEST => SurveyRefused::untrustedRequest(),
            SurveyRefused::CLOSED => SurveyRefused::closed(),
            SurveyRefused::ALREADY_ANSWERED => SurveyRefused::alreadyAnswered(),
        ] as $reason => $refused) {
            self::assertSame($reason, $refused->reason);
            self::assertNotSame('', $refused->getMessage());
        }
    }

    public function testARedirectPolicyIsReadByTheSubmissions(): void
    {
        $submissions = new Submissions(new DbalResponseRepository($this->stage->connection), new Participations(Stage::SECRET), new RedirectPolicy(), $this->stage->clock);
        $questionnaire = Questionnaire::fromArray(Samples::colour(['endings' => [['name' => 'go', 'title' => 'Go', 'redirect' => 'https://example.org/']]]));

        self::assertNull($submissions->submit($questionnaire, ['colour' => 'red'])->redirect);
    }
}
