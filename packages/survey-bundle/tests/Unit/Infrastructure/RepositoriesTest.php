<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DeadlockException;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\ValueObject\Addendum;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;
use Phil\SurveyBundle\Domain\ValueObject\Quota;
use Phil\SurveyBundle\Domain\ValueObject\Response;
use Phil\SurveyBundle\Infrastructure\Doctrine\Column;
use Phil\SurveyBundle\Infrastructure\Doctrine\DbalQuestionnaireRepository;
use Phil\SurveyBundle\Infrastructure\Doctrine\DbalResponseRepository;
use Phil\SurveyBundle\Infrastructure\Doctrine\Sql;
use Phil\SurveyBundle\Infrastructure\Doctrine\SurveyTables;
use Phil\SurveyBundle\Tests\Fixtures\Samples;
use Phil\SurveyBundle\Tests\Fixtures\Stage;
use Phil\SurveyBundle\Tests\Fixtures\WatchedConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DbalQuestionnaireRepository::class)]
#[CoversClass(DbalResponseRepository::class)]
#[CoversClass(Sql::class)]
final class RepositoriesTest extends TestCase
{
    private function watched(): WatchedConnection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => WatchedConnection::class]);
        self::assertInstanceOf(WatchedConnection::class, $connection);

        return $connection;
    }

    private function response(string $id, string $questionnaire = 'colour', ?string $subject = null, ?string $digest = null, string $at = '2026-11-21 10:00:00'): Response
    {
        return new Response($id, $questionnaire, $subject, null, 'thanks', ['colour' => 'red'], [], $digest, new DateTimeImmutable($at.' UTC'));
    }

    public function testQuestionnairesAreSavedReplacedListedAndRemovedInOrder(): void
    {
        $stage = new Stage();
        $repository = $stage->questionnaireRepository;

        foreach (['b' => 30, 'a' => 10, 'c' => null] as $key => $days) {
            $repository->save(Questionnaire::fromArray(Samples::colour(['key' => $key, 'retention_days' => $days])));
        }

        self::assertSame(['a', 'b', 'c'], $repository->keys());
        self::assertSame(['a' => 10, 'b' => 30], $repository->retentions());

        $repository->save(Questionnaire::fromArray(Samples::colour(['key' => 'a', 'title' => 'Again', 'retention_days' => 20])));
        self::assertSame(3, $stage->rows(SurveyTables::QUESTIONNAIRES));
        self::assertSame('Again', $repository->find('a')?->title);
        self::assertSame(['a' => 20, 'b' => 30], $repository->retentions());
        self::assertNull($repository->find('z'));

        $stage->responses->record($this->response('r1', 'a'), null, []);
        $stage->responses->record($this->response('r2', 'b'), null, []);
        self::assertTrue($repository->remove('a'));
        self::assertFalse($repository->remove('a'));
        self::assertSame(['b', 'c'], $repository->keys());
        self::assertSame(1, $stage->rows(SurveyTables::RESPONSES));
    }

    public function testKeysOfDigitsAreKeptAsTextInTheOrderOfText(): void
    {
        $stage = new Stage();

        foreach (['9', '10'] as $key) {
            $stage->questionnaireRepository->save(Questionnaire::fromArray(Samples::colour(['key' => $key])));
        }

        self::assertSame(['10', '9'], $stage->questionnaireRepository->keys());
    }

    public function testTheRowsATransactionTouchedAreCountedWhateverTheDriverSaysThem(): void
    {
        $connection = $this->watched();
        $stage = new Stage($connection);
        $stage->questionnaireRepository->save(Questionnaire::fromArray(Samples::colour()));

        self::assertTrue($stage->questionnaireRepository->remove('colour'));
        self::assertTrue(Sql::insertIgnoring($connection, SurveyTables::QUOTAS, ['questionnaire' => 'q', 'quota' => 'p', 'taken' => 0]));
        self::assertFalse(Sql::insertIgnoring($connection, SurveyTables::QUOTAS, ['questionnaire' => 'q', 'quota' => 'p', 'taken' => 0]));
        self::assertSame(1, $stage->responses->eraseRespondent('nobody', 'nothing') + 1);
        self::assertContains('INSERT OR IGNORE INTO phil_survey_quota_counts (questionnaire, quota, taken) VALUES (?, ?, ?)', $connection->statements);

        $stage->responses->record($this->response('old', 'q'), null, []);
        self::assertSame(1, $stage->responses->purgeBefore('q', new DateTimeImmutable('2026-12-01 UTC'), 10));
    }

    public function testAnAddendumTakesTheNextPlaceAndTheLastPlaceIsTheTenth(): void
    {
        $stage = new Stage();
        $stage->responses->record($this->response('r1', digest: hash('sha256', 'token')), null, []);

        $at = new DateTimeImmutable('2026-11-21 12:00:00 UTC');

        self::assertFalse($stage->responses->addAddendum('colour', hash('sha256', 'other'), 'x', $at, 3));
        self::assertFalse($stage->responses->addAddendum('other', hash('sha256', 'token'), 'x', $at, 3));

        foreach (['one', 'two', 'three'] as $text) {
            self::assertTrue($stage->responses->addAddendum('colour', hash('sha256', 'token'), $text, $at, 3));
        }

        self::assertFalse($stage->responses->addAddendum('colour', hash('sha256', 'token'), 'four', $at, 3));
        self::assertSame([1, 2, 3], array_map(Column::int(...), $stage->connection->fetchFirstColumn('SELECT position FROM phil_survey_addenda ORDER BY position')));
        self::assertSame(['one', 'two', 'three'], array_map(static fn (Addendum $addendum): string => $addendum->text, $stage->responses->addendaOf('r1')));
    }

    public function testAPurgeTakesTheOldestFirstAndTheirAddendaOnly(): void
    {
        $stage = new Stage();
        $at = new DateTimeImmutable('2026-11-21 12:00:00 UTC');

        foreach (['r1' => '2026-01-01 00:00:00', 'r2' => '2026-02-01 00:00:00', 'r3' => '2026-11-01 00:00:00'] as $id => $when) {
            $stage->responses->record($this->response($id, digest: hash('sha256', $id), at: $when), null, []);
            $stage->responses->addAddendum('colour', hash('sha256', $id), 'About '.$id, $at, 10);
        }

        self::assertSame(0, $stage->responses->purgeBefore('other', new DateTimeImmutable('2026-12-01 UTC'), 10));
        self::assertSame(0, $stage->responses->purgeBefore('colour', new DateTimeImmutable('2025-12-01 UTC'), 10));
        self::assertSame(1, $stage->responses->purgeBefore('colour', new DateTimeImmutable('2026-12-01 UTC'), 1));
        self::assertNull($stage->responses->find('r1'));
        self::assertNotNull($stage->responses->find('r2'));
        self::assertSame(2, $stage->rows(SurveyTables::ADDENDA));

        self::assertSame(1, $stage->responses->purgeBefore('colour', new DateTimeImmutable('2026-06-01 UTC'), 10));
        self::assertSame(['r3'], $stage->connection->fetchFirstColumn('SELECT response_id FROM phil_survey_addenda'));
        self::assertSame(1, $stage->rows(SurveyTables::RESPONSES));
    }

    public function testARecordKeepsTheMarkOfItsRespondentAndTheCountOfItsQuotas(): void
    {
        $stage = new Stage();
        $quotas = [new Quota('adults', 2, ['==' => [1, 1]], 'thanks'), new Quota('all', 5, ['==' => [1, 1]], 'thanks')];

        self::assertTrue($stage->responses->record($this->response('r1'), 'digest-1', $quotas)->recorded);
        self::assertSame(['adults' => 1, 'all' => 1], $stage->responses->quotaCounts('colour'));
        self::assertSame([['questionnaire' => 'colour', 'subject' => '']], $stage->responses->participationsOf('digest-1'));
        self::assertTrue($stage->responses->hasParticipated('colour', '', 'digest-1'));

        self::assertTrue($stage->responses->record($this->response('r2', subject: 'session:1'), 'digest-1', $quotas)->recorded);
        self::assertSame([['questionnaire' => 'colour', 'subject' => ''], ['questionnaire' => 'colour', 'subject' => 'session:1']], $stage->responses->participationsOf('digest-1'));
        self::assertSame(['adults' => 2, 'all' => 2], $stage->responses->quotaCounts('colour'));

        $again = $stage->responses->record($this->response('r3'), 'digest-1', $quotas);
        self::assertTrue($again->alreadyAnswered);
        self::assertFalse($again->recorded);
        self::assertSame(2, $stage->rows(SurveyTables::RESPONSES));
        self::assertSame(['adults' => 2, 'all' => 2], $stage->responses->quotaCounts('colour'));

        $full = $stage->responses->record($this->response('r4'), 'digest-2', $quotas);
        self::assertSame('adults', $full->fullQuota);
        self::assertSame(2, $stage->rows(SurveyTables::RESPONSES));
        self::assertSame(['adults' => 2, 'all' => 2], $stage->responses->quotaCounts('colour'));

        self::assertSame('{"colour":"red"}', $stage->connection->fetchOne('SELECT answers FROM phil_survey_responses WHERE id = ?', ['r1']));
    }

    public function testADeadlockIsRetriedUntilTheFifthTry(): void
    {
        $connection = $this->watched();
        $stage = new Stage($connection);
        $connection->deadlocks = 2;

        self::assertTrue($stage->responses->record($this->response('r1'), null, [])->recorded);
        self::assertSame(3, $connection->begun);
        self::assertSame(1, $connection->committed);
        self::assertFalse($connection->isTransactionActive());

        $connection->begun = 0;
        $connection->deadlocks = 99;

        $started = hrtime(true);

        try {
            $stage->responses->record($this->response('r2'), null, []);
            self::fail('The deadlock was not thrown.');
        } catch (DeadlockException) {
            self::assertSame(5, $connection->begun);
            self::assertGreaterThanOrEqual(10_000_000, hrtime(true) - $started, 'A retry waits 1 ms, then 2, 3 and 4 times at least that.');
        }

        self::assertNull($stage->responses->find('r2'));
    }

    public function testAJsonColumnIsWrittenAsItIsReadAndRefusedWhenItIsNotAnObject(): void
    {
        self::assertSame('{"a":"b/é","n":1.0}', Sql::json(['a' => 'b/é', 'n' => 1.0]));
        self::assertSame(['a' => 1], Sql::decode('{"a":1}'));
        self::assertSame([], Sql::decode('[]'));

        $deep = static fn (int $levels): string => str_repeat('[', $levels).str_repeat(']', $levels);
        self::assertSame(127, $this->depth(Sql::decode($deep(127))));

        foreach ([$deep(128), 'null', '"text"', '{', null, 5] as $column) {
            try {
                Sql::decode($column);
                self::fail('The column was read: '.json_encode($column));
            } catch (InvalidQuestionnaire $invalid) {
                self::assertSame('A stored column holds a JSON object.', $invalid->getMessage());
            }
        }
    }

    /**
     * @param array<mixed> $value
     */
    private function depth(array $value): int
    {
        $deepest = 0;

        foreach ($value as $inner) {
            if (\is_array($inner)) {
                $deepest = max($deepest, $this->depth($inner));
            }
        }

        return $deepest + 1;
    }

    public function testTheKeysAreReadInOrderAndAsTextWhateverTheDriverGives(): void
    {
        $connection = $this->watched();
        $stage = new Stage($connection);

        foreach (['9', '10', 'a'] as $key) {
            $stage->questionnaireRepository->save(Questionnaire::fromArray(Samples::colour(['key' => $key])));
        }

        $connection->numbersAsInts = true;
        self::assertSame(['10', '9', 'a'], $stage->questionnaireRepository->keys());
        self::assertContains('SELECT questionnaire_key FROM phil_survey_questionnaires ORDER BY questionnaire_key', $connection->statements);
    }

    public function testASubjectNarrowsTheCountAndTheAnswers(): void
    {
        $stage = new Stage();
        $stage->responses->record($this->response('r1', subject: 'session:1'), null, []);
        $stage->responses->record($this->response('r2', subject: 'session:2'), null, []);
        $stage->responses->record($this->response('r3'), null, []);

        self::assertSame(3, $stage->responses->count('colour'));
        self::assertSame(1, $stage->responses->count('colour', 'session:1'));
        self::assertSame(0, $stage->responses->count('colour', 'session:3'));
        self::assertSame(0, $stage->responses->count('other', 'session:1'));
        self::assertSame(['red'], iterator_to_array($stage->responses->answers('colour', 'colour', 'session:2'), false));
        self::assertCount(3, iterator_to_array($stage->responses->answers('colour', 'colour'), false));
    }

    public function testErasingARespondentKeepsTheOthers(): void
    {
        $stage = new Stage();
        $at = new DateTimeImmutable('2026-11-21 12:00:00 UTC');

        foreach (['ann', 'bob'] as $who) {
            $stage->responses->record(new Response('r_'.$who, 'colour', null, $who, 'thanks', ['colour' => 'red'], [], hash('sha256', $who), $at), 'mark-'.$who, []);
            $stage->responses->addAddendum('colour', hash('sha256', $who), 'About '.$who, $at, 10);
        }

        self::assertSame(1, $stage->responses->eraseRespondent('ann', 'mark-ann'));
        self::assertSame(['r_bob'], array_map(static fn (Response $response): string => $response->id, $stage->responses->ofRespondent('bob')));
        self::assertSame([], $stage->responses->ofRespondent('ann'));
        self::assertSame(1, $stage->rows(SurveyTables::RESPONSES));
        self::assertSame(['r_bob'], $stage->connection->fetchFirstColumn('SELECT response_id FROM phil_survey_addenda'));
        self::assertSame([], $stage->responses->participationsOf('mark-ann'));
        self::assertSame([['questionnaire' => 'colour', 'subject' => '']], $stage->responses->participationsOf('mark-bob'));
        self::assertSame(0, $stage->responses->eraseRespondent('ann', 'mark-ann'));
    }

    public function testAPurgeOfNothingRunsNoDelete(): void
    {
        $connection = $this->watched();
        $stage = new Stage($connection);
        $stage->responses->record($this->response('r1', 'colour'), null, []);

        $connection->forget();

        self::assertSame(0, $stage->responses->purgeBefore('colour', new DateTimeImmutable('2025-01-01 UTC'), 10));
        self::assertSame([], $connection->ran('DELETE'));
    }

    public function testARefusedRecordLeavesNoTransactionOpenAndAQuotaIsCountedWhateverTheDriverSays(): void
    {
        $connection = $this->watched();
        $stage = new Stage($connection);
        $quotas = [new Quota('adults', 1, ['==' => [1, 1]], 'thanks')];

        self::assertTrue($stage->responses->record($this->response('r1'), 'mark', $quotas)->recorded);
        self::assertSame(1, $connection->committed);

        self::assertTrue($stage->responses->record($this->response('r2'), 'mark', $quotas)->alreadyAnswered);
        self::assertFalse($connection->isTransactionActive());

        self::assertSame('adults', $stage->responses->record($this->response('r3'), 'other-mark', $quotas)->fullQuota);
        self::assertFalse($connection->isTransactionActive());
        self::assertSame(1, $connection->committed);
        self::assertSame(1, $stage->rows(SurveyTables::RESPONSES));
    }
}
