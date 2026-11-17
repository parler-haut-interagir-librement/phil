<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\DriverManager;
use JsonException;
use PDO;
use Phil\Lti\Key\KeySet;
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
use Phil\LtiBundle\Application\Contract\PendingScores;
use Phil\LtiBundle\Application\PendingScore;
use Phil\LtiBundle\Application\StoredDeepLinkingReturn;
use Phil\LtiBundle\Infrastructure\Console\PurgeCommand;
use Phil\LtiBundle\Infrastructure\Dbal\DbalAccessTokens;
use Phil\LtiBundle\Infrastructure\Dbal\DbalDeepLinkingReturns;
use Phil\LtiBundle\Infrastructure\Dbal\DbalKeyStore;
use Phil\LtiBundle\Infrastructure\Dbal\DbalLineItemStore;
use Phil\LtiBundle\Infrastructure\Dbal\DbalLoginStates;
use Phil\LtiBundle\Infrastructure\Dbal\DbalNonces;
use Phil\LtiBundle\Infrastructure\Dbal\DbalPendingScores;
use Phil\LtiBundle\Infrastructure\Dbal\DbalScoreStore;
use Phil\LtiBundle\Infrastructure\Dbal\LtiTables;
use Phil\LtiBundle\Infrastructure\Dbal\Row;
use Phil\LtiBundle\Infrastructure\KeyManagement\KeyManagementSealer;
use Phil\LtiBundle\Tests\Unit\Fixture\Interleaving;
use Phil\LtiBundle\Tests\Unit\Fixture\MysqlRowCounts;
use Phil\LtiBundle\Tests\Unit\Fixture\PlainSealer;
use Phil\LtiBundle\Tests\Unit\Fixture\StatementLog;
use Phil\LtiBundle\Tests\Unit\Fixture\Stores;
use Phil\LtiBundle\Tests\Unit\Fixture\TestKeys;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SensitiveParameter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException as KmsInvalidArgument;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;
use UnexpectedValueException;

/**
 * The edges of the stores that the shared contract does not reach: the defaults, the exact columns an update or a delete
 * is bound to, the way a payload is written and read, and the sealing of a key.
 */
#[CoversClass(DbalKeyStore::class)]
#[CoversClass(DbalLineItemStore::class)]
#[CoversClass(DbalScoreStore::class)]
#[CoversClass(DbalPendingScores::class)]
#[CoversClass(DbalDeepLinkingReturns::class)]
#[CoversClass(StoredDeepLinkingReturn::class)]
#[CoversClass(PendingScore::class)]
#[CoversClass(DbalNonces::class)]
#[CoversClass(DbalLoginStates::class)]
#[CoversClass(DbalAccessTokens::class)]
#[CoversClass(PurgeCommand::class)]
#[CoversClass(KeyManagementSealer::class)]
#[CoversClass(Row::class)]
final class StoreEdgesTest extends TestCase
{
    private MockClock $clock;

    private LtiTables $tables;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-14 12:00:00 UTC');
        $this->tables = new LtiTables();
        $this->connection = Stores::sqlite($this->tables);
    }

    public function testARetiredKeyStaysPublishedForAWeekByDefault(): void
    {
        $keys = new DbalKeyStore($this->connection, $this->tables, new PlainSealer(), $this->clock, bits: 2048);
        $first = $keys->signingKey();
        $keys->rotate();

        $this->clock->sleep(604_799);
        $keys->reset();
        self::assertCount(2, $keys->publicKeys()->keys);
        self::assertContains($first->keyId(), array_map(static fn (RsaPublicKey $key): ?string => $key->keyId, $keys->publicKeys()->keys));
        self::assertSame(0, $keys->prune());

        $this->clock->sleep(1);
        $keys->reset();
        self::assertCount(1, $keys->publicKeys()->keys);
        self::assertSame(1, $keys->prune());
    }

    public function testNoMoreKeysThanASetHoldsArePublished(): void
    {
        $json = RsaPrivateKey::fromPem(TestKeys::pem())->toJson();

        for ($index = 0; $index <= KeySet::MAX_KEYS; ++$index) {
            $kid = \sprintf('kid%02d', $index);
            $this->connection->insert($this->tables->keys, ['kid' => $kid, 'sealed' => new PlainSealer()->seal($kid, $json), 'created_at' => 1_000 + $index, 'retired_at' => null]);
        }

        $keys = new DbalKeyStore($this->connection, $this->tables, new PlainSealer(), $this->clock);

        self::assertCount(KeySet::MAX_KEYS, $keys->publicKeys()->keys);
    }

    public function testDeletingALineItemKeepsTheScoresOfTheOthers(): void
    {
        $lineItems = new DbalLineItemStore($this->connection, $this->tables);
        $scores = new DbalScoreStore($this->connection, $this->tables);
        $one = new StoredLineItem(str_repeat('a', 32), 'client', 'course', new LineItem(10.0, 'One'));
        $two = new StoredLineItem(str_repeat('b', 32), 'client', 'course', new LineItem(10.0, 'Two'));
        $lineItems->save($one);
        $lineItems->save($two);

        $scores->record($one->id, $this->score('user'));
        $scores->record($two->id, $this->score('user'));

        $lineItems->delete($one);

        self::assertSame([], $scores->scores($one->id, null, 10, 0));
        self::assertCount(1, $scores->scores($two->id, null, 10, 0));
    }

    public function testAScoreIsReplacedOnlyOnItsOwnLineItem(): void
    {
        $scores = new DbalScoreStore($this->connection, $this->tables);
        $scores->record('one', $this->score('user', 1.0));
        $scores->record('two', $this->score('user', 2.0));

        $scores->record('two', $this->score('user', 3.0));

        self::assertSame(1.0, $scores->latest('one', 'user')?->scoreGiven);
        self::assertSame(3.0, $scores->latest('two', 'user')?->scoreGiven);
    }

    public function testPayloadsAreWrittenAsReadableJson(): void
    {
        $scores = new DbalScoreStore($this->connection, $this->tables);
        $scores->record('one', $this->score('user', 1.0, 'Très bien / super'));
        self::assertStringContainsString('"comment":"Très bien / super"', $this->payload($this->tables->scores));

        $pending = new DbalPendingScores($this->connection, $this->tables);
        $pending->save(new PendingScore('p1', 'https://lms.example.org', 'client', Url::fromString('https://lms.example.org/items/1'), $this->score('user', 1.0, 'Très bien / super'), new DateTimeImmutable('2026-11-14T12:00:00+00:00')));
        self::assertStringContainsString('"comment":"Très bien / super"', $this->payload($this->tables->pendingScores));
    }

    public function testThePayloadOfAScoreIsReadAtEightLevels(): void
    {
        $scores = new DbalScoreStore($this->connection, $this->tables);
        $pending = new DbalPendingScores($this->connection, $this->tables);

        foreach ([8 => true, 9 => false] as $depth => $accepted) {
            $payload = json_encode($this->score('user')->toArray() + ['extra' => self::nested($depth - 2)], \JSON_THROW_ON_ERROR);
            $this->connection->delete($this->tables->scores, ['line_item_id' => 'deep']);
            $this->connection->insert($this->tables->scores, ['line_item_id' => 'deep', 'user_id' => 'user', 'payload' => $payload, 'scored_at' => 1]);
            $this->connection->delete($this->tables->pendingScores, ['id' => 'deep']);
            $this->connection->insert($this->tables->pendingScores, ['id' => 'deep', 'issuer' => 'i', 'client_id' => 'c', 'line_item' => 'https://lms.example.org/i', 'payload' => $payload, 'created_at' => 1]);

            if ($accepted) {
                self::assertSame('user', $scores->latest('deep', 'user')?->userId);
                self::assertSame('user', $pending->find('deep')?->score->userId);

                continue;
            }

            foreach ([static fn (): ?Score => $scores->latest('deep', 'user'), static fn (): ?PendingScore => $pending->find('deep')] as $read) {
                try {
                    $read();
                    self::fail('A payload of 9 levels was read.');
                } catch (JsonException) {
                    self::addToAssertionCount(1);
                }
            }
        }
    }

    public function testTheClaimsOfADeepLinkingReturnAreWrittenAsReadableJson(): void
    {
        $returns = new DbalDeepLinkingReturns($this->connection, $this->tables);
        $now = new DateTimeImmutable('2026-11-14 12:00:00 UTC');
        $returns->save(new StoredDeepLinkingReturn('k', 'client', ['url' => 'https://tool.example.com/é'], $now, $now->modify('+1 hour')));

        self::assertStringContainsString('"url":"https://tool.example.com/é"', $this->payload($this->tables->deepLinkingReturns));
    }

    public function testTheClaimsOfADeepLinkingReturnAreReadAtSixtyFourLevels(): void
    {
        $returns = new DbalDeepLinkingReturns($this->connection, $this->tables);
        $now = new DateTimeImmutable('2026-11-14 12:00:00 UTC');

        // The innermost empty array counts for a level of its own: 63 brackets are 64 levels.
        foreach ([63 => true, 64 => false] as $levels => $accepted) {
            $this->connection->delete($this->tables->deepLinkingReturns, ['id' => 'deep']);
            $this->connection->insert($this->tables->deepLinkingReturns, ['id' => 'deep', 'client_id' => 'c', 'payload' => str_repeat('[', $levels).str_repeat(']', $levels), 'received_at' => 1, 'expires_at' => $now->getTimestamp() + 3600]);

            if ($accepted) {
                self::assertNotNull($returns->find('deep', $now));

                continue;
            }

            try {
                $returns->find('deep', $now);
                self::fail('Claims of 64 levels were read.');
            } catch (JsonException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTheRefusedScoresAreKeptThirtyDaysByDefault(): void
    {
        $pending = new DbalPendingScores($this->connection, $this->tables);
        $score = new Score('learner', ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('2026-11-14T12:00:00+00:00'), 1.0, 1.0);
        $now = $this->clock->now();

        foreach (['exact' => 2_592_000, 'recent' => 2_591_999] as $id => $age) {
            $pending->save(new PendingScore($id, 'https://lms.example.org', 'client', Url::fromString('https://lms.example.org/items/1'), $score, $now));
            $pending->markRefused($id, 'Refused', $now->modify('-'.$age.' seconds'));
        }

        $output = new BufferedOutput();
        new PurgeCommand(new DbalNonces($this->connection, $this->tables, $this->clock), $this->clock, pending: $pending)(new SymfonyStyle(new ArrayInput([]), $output));

        self::assertNull($pending->find('exact'));
        self::assertNotNull($pending->find('recent'));
        self::assertStringContainsString('1 refused scores.', (string) preg_replace('/\s+/', ' ', $output->fetch()));
    }

    public function testAScoreListedWithoutTheMomentItWasRefusedIsShownWithoutIt(): void
    {
        $score = new PendingScore('odd', 'https://lms.example.org', 'client', Url::fromString('https://lms.example.org/items/1'), $this->score('learner'), $this->clock->now());
        $pending = new class($score) implements PendingScores {
            private bool $listed = false;

            public function __construct(
                private readonly PendingScore $score,
            ) {
            }

            public function save(PendingScore $score): void
            {
            }

            public function find(string $id): ?PendingScore
            {
                return null;
            }

            public function markDispatched(string $id, DateTimeImmutable $at): void
            {
            }

            public function markRefused(string $id, string $reason, DateTimeImmutable $at): void
            {
            }

            public function claim(DateTimeImmutable $before, DateTimeImmutable $now, int $limit): array
            {
                return [];
            }

            public function refused(DateTimeImmutable $before, int $limit): array
            {
                if ($this->listed) {
                    return [];
                }

                $this->listed = true;

                return [$this->score];
            }

            public function delete(string $id): void
            {
            }
        };

        $output = new BufferedOutput();
        new PurgeCommand(new DbalNonces($this->connection, $this->tables, $this->clock), $this->clock, pending: $pending)(new SymfonyStyle(new ArrayInput([]), $output));

        self::assertStringContainsString('Refused score odd of https://lms.example.org, client client, refused at : ', (string) preg_replace('/\s+/', ' ', $output->fetch()));
    }

    public function testAScoreIsWrittenInTheFewestStatements(): void
    {
        $log = new StatementLog();
        $interleaving = new Interleaving();
        $connection = $this->logged([$interleaving, $log->middleware()]);
        $scores = new DbalScoreStore($connection, $this->tables);
        $at = static fn (string $time, float $given): Score => new Score('u', ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('2026-11-14T'.$time.'+00:00'), $given, 10.0);
        $count = static fn (string $prefix): int => $log->count($prefix);

        self::assertTrue($scores->record('item', $at('12:00:00', 1.0)));
        self::assertSame([1, 1], [$count('UPDATE'), $count('INSERT')], 'A new score is tried as an update, then inserted, and nothing more.');

        self::assertTrue($scores->record('item', $at('12:00:01', 2.0)));
        self::assertSame([2, 1], [$count('UPDATE'), $count('INSERT')], 'A score that replaces another is not inserted.');

        $connection->executeStatement('DELETE FROM '.$this->tables->scores);
        $log->statements = [];
        $table = $this->tables->scores;
        $interleaving->before('BEGIN', static function (DriverConnection $driver) use ($table): void {
            $driver->exec(\sprintf("INSERT INTO %s (line_item_id, user_id, payload, scored_at) VALUES ('item', 'u', '{}', 1)", $table));
        });

        self::assertTrue($scores->record('item', $at('12:00:00', 3.0)));
        self::assertSame([0], [$count('SELECT scored_at')], 'A score that replaces the one written meanwhile asks nothing more.');
    }

    public function testAScoreWrittenAgainAsItIsIsKeptWhateverTheDatabaseCounts(): void
    {
        $connection = $this->logged([new MysqlRowCounts()], [PDO::ATTR_STRINGIFY_FETCHES => true]);
        $scores = new DbalScoreStore($connection, $this->tables);
        $score = $this->score('u', 1.0);

        self::assertTrue($scores->record('item', $score));
        self::assertTrue($scores->record('item', $score), 'The moment kept is the moment of the score, however the database counts and gives it.');
        self::assertFalse($scores->record('item', new Score('u', ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('2026-11-14T11:59:59+00:00'), 1.0, 1.0)));
    }

    /**
     * @param list<Middleware>  $middlewares
     * @param array<int, mixed> $options
     */
    private function logged(array $middlewares, array $options = []): Connection
    {
        return Stores::prepare(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'driverOptions' => $options], new Configuration()->setMiddlewares($middlewares)), $this->tables);
    }

    public function testAPendingScoreOfALocalAddressIsReadOnlyWhenLocalhostIsAllowed(): void
    {
        $this->connection->insert($this->tables->pendingScores, ['id' => 'p', 'issuer' => 'i', 'client_id' => 'c', 'line_item' => 'https://lms.localhost/i', 'payload' => json_encode($this->score('user')->toArray(), \JSON_THROW_ON_ERROR), 'created_at' => 1]);

        self::assertSame('https://lms.localhost/i', new DbalPendingScores($this->connection, $this->tables, true)->find('p')?->lineItem->toString());

        $this->expectException(InvalidUrl::class);
        new DbalPendingScores($this->connection, $this->tables)->find('p');
    }

    public function testDeletingAPendingScoreKeepsTheOthers(): void
    {
        $pending = new DbalPendingScores($this->connection, $this->tables);

        foreach (['p1', 'p2'] as $id) {
            $pending->save(new PendingScore($id, 'https://lms.example.org', 'client', Url::fromString('https://lms.example.org/items/1'), $this->score('user'), new DateTimeImmutable('2026-11-14T12:00:00+00:00')));
        }

        $pending->delete('p1');

        self::assertNull($pending->find('p1'));
        self::assertNotNull($pending->find('p2'));
    }

    public function testAnIntegerColumnIsDigitsAndNothingAfterThem(): void
    {
        self::assertSame(-12, Row::int(['a' => '-12'], 'a'));
        self::assertSame(9_223_372_036_854_775_807, Row::int(['a' => '9223372036854775807'], 'a'));

        foreach (["3\n", '3 ', ' 3', '3.5', '', '-', 'x3', '12345678901234567890'] as $value) {
            try {
                Row::int(['a' => $value], 'a');
                self::fail(var_export($value, true).' was read.');
            } catch (UnexpectedValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testThePurgeCountsWhatEachStoreRemoves(): void
    {
        $nonces = new DbalNonces($this->connection, $this->tables, $this->clock);
        $states = new DbalLoginStates($this->connection, $this->tables, $this->clock);
        $tokens = new DbalAccessTokens($this->connection, $this->tables, $this->clock);
        $nonces->use('s', 'v', new DateTimeImmutable('2026-11-14 12:00:01 UTC'));
        $states->save(new LoginState('state', 'nonce', 'https://lms.example.org', 'client', 'https://tool.example.com/launch', new DateTimeImmutable('2026-11-14 12:00:01 UTC')));
        $tokens->save(new AccessToken('hash', 'client', [Scope::SCORE], new DateTimeImmutable('2026-11-14 12:00:01 UTC')));
        $pending = new DbalPendingScores($this->connection, $this->tables);
        $returns = new DbalDeepLinkingReturns($this->connection, $this->tables);
        $score = new Score('learner', ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('2026-11-14T12:00:00+00:00'), 1.0, 1.0);

        for ($i = 0; $i < PurgeCommand::BATCH + 1; ++$i) {
            $pending->save(new PendingScore(\sprintf('old-%03d', $i), 'https://lms.example.org', 'client', Url::fromString('https://lms.example.org/items/1'), $score, $this->clock->now()));
            $pending->markRefused(\sprintf('old-%03d', $i), 'Refused: 404', new DateTimeImmutable('2026-11-13 11:59:59 UTC'));
        }

        $pending->save(new PendingScore('recent', 'https://lms.example.org', 'client', Url::fromString('https://lms.example.org/items/1'), $score, $this->clock->now()));
        $pending->markRefused('recent', 'Refused: 400', new DateTimeImmutable('2026-11-13 12:00:03 UTC'));
        $pending->save(new PendingScore('waiting', 'https://lms.example.org', 'client', Url::fromString('https://lms.example.org/items/1'), $score, new DateTimeImmutable('2026-11-01 12:00:00 UTC')));

        $returns->save(new StoredDeepLinkingReturn('gone', 'client', [], $this->clock->now(), new DateTimeImmutable('2026-11-14 12:00:02 UTC')));
        $returns->save(new StoredDeepLinkingReturn('kept', 'client', [], $this->clock->now(), new DateTimeImmutable('2026-11-14 12:00:03 UTC')));

        $this->clock->sleep(2);

        $output = new BufferedOutput();
        self::assertSame(0, new PurgeCommand($nonces, $this->clock, $states, $tokens, $pending, $returns, 86_400)(new SymfonyStyle(new ArrayInput([]), $output)));

        $display = (string) preg_replace('/\s+/', ' ', $output->fetch());
        self::assertStringContainsString('Removed 1 nonces, 1 login states, 1 access tokens, 1 deep linking responses and 101 refused scores.', $display);
        self::assertStringContainsString('Refused score old-000 of https://lms.example.org, client client, refused at 2026-11-13T11:59:59+00:00: Refused: 404', $display);
        self::assertStringContainsString('Refused score old-100 of', $display);
        self::assertStringNotContainsString('learner', $display);
        self::assertNull($pending->find('old-100'));
        self::assertNotNull($pending->find('recent'));
        self::assertNotNull($pending->find('waiting'));
        self::assertNull($returns->find('gone', new DateTimeImmutable('2026-11-14 12:00:00 UTC')));
        self::assertNotNull($returns->find('kept', $this->clock->now()));
    }

    public function testASealedKeyIsBoundToItsIdentifierByAFixedContext(): void
    {
        $kms = new SodiumKms(new InMemoryKeyLoader(['lti' => str_repeat('k', 32)]));
        $sealed = sodium_bin2base64((string) new EnvelopeEncrypter($kms)->encrypt('lti', 'pem', 'phil_lti.key:kid-1'), \SODIUM_BASE64_VARIANT_ORIGINAL);

        self::assertSame('pem', new KeyManagementSealer($kms, 'lti')->open('kid-1', $sealed));
    }

    public function testEveryFailureOfTheKmsIsAnExceptionOfTheSealerWithoutCode(): void
    {
        $kms = new SodiumKms(new InMemoryKeyLoader(['lti' => str_repeat('k', 32)]));
        $sealer = new KeyManagementSealer($kms, 'lti');
        $sealed = $sealer->seal('kid-1', 'pem');
        $broken = new class implements DataKeyGeneratorInterface {
            public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
            {
                throw new RuntimeException('boom');
            }

            public function wrapDataKey(string $keyId, #[SensitiveParameter] string $dataKey, string $aad = ''): Ciphertext
            {
                throw new RuntimeException('boom');
            }

            public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
            {
                throw new RuntimeException('boom');
            }
        };

        $refusing = new class implements DataKeyGeneratorInterface {
            public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
            {
                throw new KmsInvalidArgument('refused');
            }

            public function wrapDataKey(string $keyId, #[SensitiveParameter] string $dataKey, string $aad = ''): Ciphertext
            {
                throw new KmsInvalidArgument('refused');
            }

            public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
            {
                throw new KmsInvalidArgument('refused');
            }
        };

        $cases = [
            'sealing with a refusing KMS' => [static fn (): string => new KeyManagementSealer($refusing, 'lti')->seal('kid-1', 'pem'), 'The key "kid-1" cannot be sealed: refused'],
            'sealing with an unknown master key' => [static fn (): string => new KeyManagementSealer($kms, 'missing')->seal('kid-1', 'pem'), 'The key "kid-1" cannot be sealed: '],
            'sealing with a failing KMS' => [static fn (): string => new KeyManagementSealer($broken, 'lti')->seal('kid-1', 'pem'), 'The key "kid-1" cannot be sealed: boom'],
            'opening under another identifier' => [static fn (): string => $sealer->open('kid-2', $sealed), 'The sealed key "kid-2" does not open: '],
            'opening with a failing KMS' => [static fn (): string => new KeyManagementSealer($broken, 'lti')->open('kid-1', $sealed), 'The sealed key "kid-1" does not open: boom'],
            'opening what is not base 64' => [static fn (): string => $sealer->open('kid-1', 'not base 64!'), 'The sealed key "kid-1" is not in base 64.'],
            'opening what is no envelope' => [static fn (): string => $sealer->open('kid-1', base64_encode('garbage')), 'The sealed key "kid-1" does not open: '],
        ];

        foreach ($cases as $name => [$call, $message]) {
            try {
                $call();
                self::fail($name.' succeeded.');
            } catch (RuntimeException $exception) {
                self::assertStringStartsWith($message, $exception->getMessage(), $name);
                self::assertSame(0, $exception->getCode(), $name);
                self::assertNotNull($exception->getPrevious(), $name);
            }
        }
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    private static function nested(int $levels): array
    {
        return 1 === $levels ? [] : [self::nested($levels - 1)];
    }

    private function score(string $user, ?float $given = null, ?string $comment = null): Score
    {
        return new Score($user, ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('2026-11-14T12:00:00+00:00'), $given, $given, $comment);
    }

    private function payload(string $table): string
    {
        $payload = $this->connection->fetchOne('SELECT payload FROM '.$table);
        self::assertIsString($payload);

        return $payload;
    }
}
