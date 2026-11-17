<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Infrastructure;

use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use InvalidArgumentException;
use Jose\Component\Signature\Algorithm\RS256;
use Phil\Lti\Key\RsaPrivateKey;
use Phil\Lti\Key\RsaPublicKey;
use Phil\Lti\Platform\Ags\StoredLineItem;
use Phil\Lti\Platform\Token\AccessToken;
use Phil\Lti\Security\LoginState;
use Phil\Lti\Service\Ags\ActivityProgress;
use Phil\Lti\Service\Ags\GradingProgress;
use Phil\Lti\Service\Ags\LineItem;
use Phil\Lti\Service\Ags\Score;
use Phil\Lti\Service\Scope;
use Phil\LtiBundle\Application\PendingScore;
use Phil\LtiBundle\Application\StoredDeepLinkingReturn;
use Phil\LtiBundle\Infrastructure\Console\SchemaCommand;
use Phil\LtiBundle\Infrastructure\Dbal\DbalAccessTokens;
use Phil\LtiBundle\Infrastructure\Dbal\DbalDeepLinkingReturns;
use Phil\LtiBundle\Infrastructure\Dbal\DbalKeyStore;
use Phil\LtiBundle\Infrastructure\Dbal\DbalLineItemStore;
use Phil\LtiBundle\Infrastructure\Dbal\DbalLoginStates;
use Phil\LtiBundle\Infrastructure\Dbal\DbalNonces;
use Phil\LtiBundle\Infrastructure\Dbal\DbalPendingScores;
use Phil\LtiBundle\Infrastructure\Dbal\DbalScoreStore;
use Phil\LtiBundle\Infrastructure\Dbal\LtiTables;
use Phil\LtiBundle\Tests\Unit\Fixture\Contracts;
use Phil\LtiBundle\Tests\Unit\Fixture\Interleaving;
use Phil\LtiBundle\Tests\Unit\Fixture\PlainSealer;
use Phil\Url\Url;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The contract of the stores on a connection: SQLite here, MySQL and PostgreSQL in the integration suite.
 */
abstract class StoresTestCase extends TestCase
{
    use Contracts;

    protected MockClock $clock;

    protected LtiTables $tables;

    protected Interleaving $interleaving;

    abstract protected function connection(): Connection;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-14 12:00:00 UTC');
        $this->tables = new LtiTables('phil_lti_test_');
        $this->interleaving = new Interleaving();
    }

    public function testANonceIsUsedOnceUntilItExpires(): void
    {
        $nonces = new DbalNonces($this->connection(), $this->tables, $this->clock);
        $expiry = new DateTimeImmutable('2026-11-14 12:01:00 UTC');

        self::assertTrue($nonces->use('id_token', 'n', $expiry));
        self::assertFalse($nonces->use('id_token', 'n', $expiry));
        self::assertTrue($nonces->use('jti', 'n', $expiry));
        self::assertTrue($nonces->use('id_token', 'N', $expiry));
        $this->clock->sleep(59);
        self::assertFalse($nonces->use('id_token', 'n', $expiry));
        $this->clock->sleep(1);
        self::assertTrue($nonces->use('id_token', 'n', $expiry->modify('+1 minute')));
        self::assertFalse($nonces->use('id_token', 'n', $expiry));
        self::assertSame(2, $nonces->purge());
        self::assertSame(0, $nonces->purge());
    }

    public function testAStateIsTakenOnce(): void
    {
        $states = new DbalLoginStates($this->connection(), $this->tables, $this->clock);
        $state = new LoginState('state-1', 'nonce-1', 'https://lms.example.org', 'client', 'https://tool.example.com/launch', new DateTimeImmutable('2026-11-14 12:10:00 UTC'));
        $states->save($state);

        $taken = $states->take('state-1');
        self::assertEquals($state, $taken);
        self::assertNull($states->take('state-1'));
        self::assertNull($states->take('other'));

        $states->save($state);
        $this->clock->sleep(600);
        self::assertNull($states->take('state-1'));
        $states->save($state);
        self::assertSame(1, $states->purge());
    }

    public function testAnAccessTokenIsFoundUntilItExpires(): void
    {
        $tokens = new DbalAccessTokens($this->connection(), $this->tables, $this->clock);
        $tokens->save(new AccessToken('hash-1', 'client', [Scope::SCORE, Scope::LINE_ITEM], new DateTimeImmutable('2026-11-14 13:00:00 UTC')));
        $tokens->save(new AccessToken('hash-2', 'client', [], new DateTimeImmutable('2026-11-14 12:30:00 UTC')));

        self::assertEquals(new AccessToken('hash-1', 'client', [Scope::SCORE, Scope::LINE_ITEM], new DateTimeImmutable('2026-11-14 13:00:00 UTC')), $tokens->find('hash-1'));
        self::assertSame([], $tokens->find('hash-2')?->scopes);
        self::assertNull($tokens->find('nope'));
        $this->clock->sleep(1800);
        self::assertNull($tokens->find('hash-2'));
        self::assertSame(1, $tokens->purge());
        self::assertNotNull($tokens->find('hash-1'));
    }

    public function testLineItemsAndScoresAreKept(): void
    {
        $connection = $this->connection();
        $lineItems = new DbalLineItemStore($connection, $this->tables);
        $scores = new DbalScoreStore($connection, $this->tables);
        $item = new StoredLineItem(str_repeat('a', 32), 'client', 'course-É', new LineItem(10.5, 'Quiz', null, 'r', 'link', 'tag', new DateTimeImmutable('2026-11-14T00:00:00+00:00'), new DateTimeImmutable('2026-11-20T00:00:00-05:00'), true));
        $other = new StoredLineItem(str_repeat('b', 32), 'client', 'course-É', new LineItem(1.0, 'Essay', gradesReleased: false));
        $lineItems->save($item);
        $lineItems->save($other);
        $lineItems->save(new StoredLineItem(str_repeat('c', 32), 'other', 'course-É', new LineItem(1.0, 'Foreign')));

        self::assertEquals($item, $lineItems->find('client', 'course-É', $item->id));
        self::assertNull($lineItems->find('client', 'course-e', $item->id));
        self::assertNull($lineItems->find('other', 'course-É', $item->id));
        self::assertEquals([$item, $other], $lineItems->list('client', 'course-É', null, null, null, 10, 0));
        self::assertEquals([$other], $lineItems->list('client', 'course-É', null, null, null, 10, 1));
        self::assertEquals([$item], $lineItems->list('client', 'course-É', null, null, null, 1, 0));
        self::assertEquals([$item], $lineItems->list('client', 'course-É', 'link', 'r', 'tag', 10, 0));
        self::assertSame([], $lineItems->list('client', 'course-É', 'nope', null, null, 10, 0));
        self::assertSame([], $lineItems->list('client', 'course-É', null, 'nope', null, 10, 0));
        self::assertSame([], $lineItems->list('client', 'course-É', null, null, 'nope', 10, 0));
        self::assertFalse($lineItems->find('client', 'course-É', $other->id)?->lineItem->gradesReleased);

        $changed = new StoredLineItem($item->id, 'client', 'course-É', new LineItem(20.0, 'Quiz 2'));
        $lineItems->save($changed);
        self::assertEquals($changed, $lineItems->find('client', 'course-É', $item->id));

        $first = new Score('user-2', ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('2026-11-14T12:00:00.250+00:00'), 7.0, 10.0, 'Bien');
        $scores->record($item->id, $first);
        $scores->record($item->id, new Score('user-1', ActivityProgress::Started, GradingProgress::Pending, new DateTimeImmutable('2026-11-14T12:00:00+00:00')));
        self::assertEquals($first, $scores->latest($item->id, 'user-2'));
        self::assertNull($scores->latest($item->id, 'user-3'));
        $second = new Score('user-2', ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('2026-11-14T13:00:00+00:00'), 9.0, 10.0);
        $scores->record($item->id, $second);
        self::assertEquals($second, $scores->latest($item->id, 'user-2'));
        self::assertSame(['user-1', 'user-2'], array_map(static fn (Score $score): string => $score->userId, $scores->scores($item->id, null, 10, 0)));
        self::assertSame(['user-2'], array_map(static fn (Score $score): string => $score->userId, $scores->scores($item->id, null, 10, 1)));
        self::assertSame(['user-1'], array_map(static fn (Score $score): string => $score->userId, $scores->scores($item->id, null, 1, 0)));
        self::assertEquals([$second], $scores->scores($item->id, 'user-2', 10, 0));

        $lineItems->delete($changed);
        self::assertNull($lineItems->find('client', 'course-É', $item->id));
        self::assertSame([], $scores->scores($item->id, null, 10, 0));
        self::assertNotNull($lineItems->find('client', 'course-É', $other->id));
    }

    public function testPendingScoresAreKeptUntilSent(): void
    {
        $pending = new DbalPendingScores($this->connection(), $this->tables);
        $score = new PendingScore('p1', 'https://lms.example.org', 'client', Url::fromString('https://lms.example.org/items/1?x=1'), new Score('u', ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('2026-11-14T12:00:00+00:00'), 1.0, 2.0), new DateTimeImmutable('2026-11-14T12:00:00+00:00'));
        $pending->save($score);

        self::assertEquals($score, $pending->find('p1'));
        self::assertNull($pending->find('p2'));
        $pending->delete('p1');
        self::assertNull($pending->find('p1'));
    }

    public function testAnOlderScoreNeverReplacesAMoreRecentOne(): void
    {
        $scores = new DbalScoreStore($this->connection(), $this->tables);
        $at = static fn (string $time, float $given): Score => new Score('u', ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('2026-11-14T'.$time), $given, 10.0);

        self::assertTrue($scores->record('item', $at('12:01:00.500+00:00', 5.0)));
        self::assertFalse($scores->record('item', $at('12:01:00.499+00:00', 1.0)));
        self::assertFalse($scores->record('item', $at('13:00:00+01:00', 1.0)), 'The moment is compared in UTC.');
        self::assertSame(5.0, $this->given($scores, 'item'));
        self::assertEquals(1_794_657_660_500_000, $this->connection()->fetchOne("SELECT scored_at FROM phil_lti_test_score WHERE line_item_id = 'item'"), 'The moment is kept in microseconds since the epoch.');
        self::assertTrue($scores->record('item', $at('12:01:00.500+00:00', 6.0)), 'A score of the same moment replaces the one kept.');
        self::assertSame(6.0, $this->given($scores, 'item'));
        self::assertTrue($scores->record('item', $at('12:01:00.500+00:00', 6.0)), 'A score written again as it is is kept.');
        self::assertTrue($scores->record('item', $at('07:01:00.501-05:00', 7.0)));
        self::assertSame(7.0, $this->given($scores, 'item'));
        self::assertFalse($scores->record('item', new Score('u', ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('1969-12-31T23:59:59.5+00:00'))));
        self::assertTrue($scores->record('early', new Score('u', ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('1969-12-31T23:59:59.5+00:00'))));
        self::assertFalse($scores->record('early', new Score('u', ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('1969-12-31T23:59:59.4+00:00'))), 'A moment before 1970 counts its microseconds backwards from the second after it.');
        self::assertTrue($scores->record('early', new Score('u', ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('1969-12-31T23:59:59.6+00:00'))));
        self::assertEquals(2, $this->connection()->fetchOne('SELECT COUNT(*) FROM phil_lti_test_score'));
    }

    public function testAScoreInsertedFirstByAnotherWriterIsComparedAgain(): void
    {
        $scores = new DbalScoreStore($this->connection(), $this->tables);
        $score = static fn (string $time, float $given): Score => new Score('u', ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('2026-11-14T'.$time.'+00:00'), $given, 10.0);
        $other = function (Score $score): Closure {
            $table = $this->tables->scores;

            return static function (DriverConnection $connection) use ($table, $score): void {
                $connection->exec(\sprintf(
                    "INSERT INTO %s (line_item_id, user_id, payload, scored_at) VALUES ('item', 'u', %s, %d)",
                    $table,
                    $connection->quote(json_encode($score->toArray(), \JSON_THROW_ON_ERROR)),
                    $score->timestamp->getTimestamp() * 1_000_000 + (int) $score->timestamp->format('u'),
                ));
            };
        };

        $this->interleaving->before('BEGIN', $other($score('11:00:00', 1.0)));
        self::assertTrue($scores->record('item', $score('12:00:00', 5.0)), 'An older score written meanwhile gives way.');
        self::assertSame(5.0, $this->given($scores, 'item'));

        $this->connection()->executeStatement('DELETE FROM phil_lti_test_score');
        $this->interleaving->before('BEGIN', $other($score('13:00:00', 9.0)));
        self::assertFalse($scores->record('item', $score('12:00:00', 5.0)), 'A more recent score written meanwhile stays.');
        self::assertSame(9.0, $this->given($scores, 'item'));

        $this->connection()->executeStatement('DELETE FROM phil_lti_test_score');
        $this->interleaving->before('BEGIN', $other($score('12:00:00', 9.0)));
        self::assertTrue($scores->record('item', $score('12:00:00', 5.0)), 'A score of the same moment written meanwhile is replaced.');
        self::assertSame(5.0, $this->given($scores, 'item'));
    }

    public function testAScoreIsInsertedInASavepointOfTheTransactionOfTheCaller(): void
    {
        $connection = $this->connection();
        $scores = new DbalScoreStore($connection, $this->tables);
        $score = static fn (string $time, float $given): Score => new Score('u', ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('2026-11-14T'.$time.'+00:00'), $given, 10.0);
        $scores->record('item', $score('13:00:00', 9.0));

        $connection->transactional(static function () use ($scores, $score): void {
            self::assertFalse($scores->record('item', $score('12:00:00', 1.0)));
            self::assertTrue($scores->record('other', $score('12:00:00', 1.0)));
        });

        self::assertSame(1.0, $this->given($scores, 'other'));
        self::assertSame(9.0, $this->given($scores, 'item'));
    }

    private function given(DbalScoreStore $scores, string $lineItemId): ?float
    {
        $score = $scores->latest($lineItemId, 'u');

        return $score instanceof Score ? $score->scoreGiven : null;
    }

    public function testThePendingScoresKeepTheirStateAndAreClaimedOnce(): void
    {
        self::checkPendingScores(new DbalPendingScores($this->connection(), $this->tables));
    }

    public function testAPendingScoreKeepsEveryFieldItIsSavedWith(): void
    {
        $pending = new DbalPendingScores($this->connection(), $this->tables);
        $score = new PendingScore('full', 'https://lms.example.org', 'client', Url::fromString('https://lms.example.org/items/1'), new Score('u', ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('2026-11-14T12:00:00+00:00'), 1.0, 2.0), new DateTimeImmutable('2026-11-14T11:00:00+00:00'), new DateTimeImmutable('2026-11-14T11:00:01+00:00'), new DateTimeImmutable('2026-11-14T11:00:02+00:00'), 'Refused');
        $pending->save($score);

        self::assertEquals($score, $pending->find('full'));
    }

    public function testTheDeepLinkingReturnsAreKeptUntilTheyExpire(): void
    {
        self::checkDeepLinkingReturns(new DbalDeepLinkingReturns($this->connection(), $this->tables));
    }

    public function testTheSchemaCommandAddsTheColumnsAndIndexesATableLacks(): void
    {
        $connection = $this->connection();
        $platform = $connection->getDatabasePlatform();
        $connection->executeStatement('DROP TABLE '.$this->tables->pendingScores);
        $connection->executeStatement('DROP TABLE '.$this->tables->deepLinkingReturns);

        $first = Table::editor()->setUnquotedName($this->tables->pendingScores)
            ->setColumns(
                Column::editor()->setUnquotedName('id')->setTypeName(Types::STRING)->setLength(64)->create(),
                Column::editor()->setUnquotedName('issuer')->setTypeName(Types::STRING)->setLength(1024)->create(),
                Column::editor()->setUnquotedName('client_id')->setTypeName(Types::STRING)->setLength(255)->create(),
                Column::editor()->setUnquotedName('line_item')->setTypeName(Types::STRING)->setLength(2048)->create(),
                Column::editor()->setUnquotedName('payload')->setTypeName(Types::TEXT)->create(),
                Column::editor()->setUnquotedName('created_at')->setTypeName(Types::BIGINT)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->create();

        foreach ($platform->getCreateTablesSQL([$first]) as $statement) {
            $connection->executeStatement($statement);
        }

        $command = new SchemaCommand($connection, $this->tables);
        $run = static function (bool $force) use ($command): string {
            $output = new BufferedOutput();
            self::assertSame(0, $command(new SymfonyStyle(new ArrayInput([]), $output), $force));

            return (string) preg_replace('/\s+/', ' ', $output->fetch());
        };

        self::assertStringContainsString('1 tables missing, 1 tables to change', $run(false));
        self::assertStringContainsString('Created: phil_lti_test_deep_linking. Changed: phil_lti_test_pending_score.', $run(true));
        self::assertStringContainsString('The LTI tables are up to date.', $run(false));
        self::checkPendingScores(new DbalPendingScores($connection, $this->tables));
    }

    public function testTheClaimsOfADeepLinkingReturnAreBounded(): void
    {
        $returns = new DbalDeepLinkingReturns($this->connection(), $this->tables);
        $now = new DateTimeImmutable('2026-11-14 12:00:00 UTC');
        $returns->save(new StoredDeepLinkingReturn('big', 'client', ['x' => str_repeat('a', DbalDeepLinkingReturns::MAX_PAYLOAD - 8)], $now, $now->modify('+1 hour')));
        self::assertNotNull($returns->find('big', $now));

        try {
            $returns->save(new StoredDeepLinkingReturn('bigger', 'client', ['x' => str_repeat('a', DbalDeepLinkingReturns::MAX_PAYLOAD - 7)], $now, $now->modify('+1 hour')));
            self::fail('Claims beyond the bound were kept.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('The claims of a deep linking response are kept up to 65535 bytes of JSON.', $invalidArgumentException->getMessage());
        }

        self::assertNull($returns->find('bigger', $now));
    }

    public function testKeysAreMadeRotatedAndRetired(): void
    {
        $sealer = new PlainSealer();
        $connection = $this->connection();
        $keys = new DbalKeyStore($connection, $this->tables, $sealer, $this->clock, 3600, 2048);

        self::assertTrue($keys->isDue(60));
        $first = $keys->signingKey();
        self::assertSame(16, \strlen($first->keyId()));
        self::assertSame($first, $keys->signingKey());
        self::assertSame([$first->keyId()], array_map(static fn (RsaPublicKey $key): ?string => $key->keyId, $keys->publicKeys()->keys));
        self::assertSame($keys->publicKeys(), $keys->publicKeys());
        self::assertFalse($keys->isDue(60));
        $this->clock->sleep(60);
        self::assertTrue($keys->isDue(60));
        self::assertFalse($keys->isDue(61));

        $sealed = $connection->fetchOne('SELECT sealed FROM phil_lti_test_key');
        self::assertIsString($sealed);
        self::assertStringStartsWith($first->keyId().'|', $sealed);

        $this->clock->sleep(1);
        $second = $keys->rotate();
        self::assertSame($second->keyId(), $keys->signingKey()->keyId());
        self::assertSame([$second->keyId(), $first->keyId()], array_map(static fn (RsaPublicKey $key): ?string => $key->keyId, $keys->publicKeys()->keys));
        self::assertSame(0, $keys->prune());

        $this->clock->sleep(3600);
        $keys->reset();
        self::assertSame([$second->keyId()], array_map(static fn (RsaPublicKey $key): ?string => $key->keyId, $keys->publicKeys()->keys));
        self::assertSame(1, $keys->prune());

        $fresh = new DbalKeyStore($connection, $this->tables, $sealer, $this->clock);
        self::assertSame($second->keyId(), $fresh->signingKey()->keyId());
        self::assertTrue(new RS256()->verify($fresh->signingKey()->publicKey->jwk, 'x', new RS256()->sign($second->jwk(), 'x')));
    }

    public function testAnEmptyTablePublishesTheFirstKeyItMakes(): void
    {
        $keys = new DbalKeyStore($this->connection(), $this->tables, new PlainSealer(), $this->clock);

        self::assertCount(1, $keys->publicKeys()->keys);
        self::assertSame($keys->publicKeys()->keys[0]->keyId, $keys->signingKey()->keyId());
        self::assertInstanceOf(RsaPrivateKey::class, $keys->signingKey());
    }
}
