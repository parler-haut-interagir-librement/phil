<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Application;

use Override;
use Phil\Idempotency\IdempotencyGuard;
use Phil\Idempotency\Store\CacheIdempotencyStore;
use Phil\SitesConsoleBundle\Application\Agent\AgentEndpoints;
use Phil\SitesConsoleBundle\Application\Agent\ConsoleKeyRing;
use Phil\SitesConsoleBundle\Application\Agent\RequestVerifier;
use Phil\SitesConsoleBundle\Application\Agent\VerifiedRequest;
use Phil\SitesConsoleBundle\Application\Contract\ConsoleKeyStore;
use Phil\SitesConsoleBundle\Application\Exception\ActionUnavailable;
use Phil\SitesConsoleBundle\Application\Exception\KeyRefused;
use Phil\SitesConsoleBundle\Application\Exception\StateNotKept;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;
use Phil\SitesConsoleBundle\Domain\Exception\SignatureRefused;
use Phil\SitesConsoleBundle\Domain\Signature\ConsoleKeys;
use Phil\SitesConsoleBundle\Domain\Signature\Protocol;
use Phil\SitesConsoleBundle\Domain\Signature\SigningKey;
use Phil\SitesConsoleBundle\Domain\Signature\VerifyingKey;
use Phil\SitesConsoleBundle\Domain\Status\KeyOperation;
use Phil\SitesConsoleBundle\Domain\ValueObject\KeyRequest;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemoryConsoleKeyStore;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Keys;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Memory;
use Phil\SitesConsoleBundle\Tests\Unit\Support\RecordingLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * The console keys an agent accepts: two during a rotation, the store that keeps what a rotation changed, and the
 * signed requests that stage and retire them.
 */
#[CoversClass(ConsoleKeys::class)]
#[CoversClass(ConsoleKeyRing::class)]
#[CoversClass(VerifiedRequest::class)]
#[CoversClass(KeyRequest::class)]
#[CoversClass(KeyRefused::class)]
#[CoversClass(StateNotKept::class)]
#[CoversClass(InMemoryConsoleKeyStore::class)]
#[CoversClass(VerifyingKey::class)]
final class ConsoleKeysTest extends TestCase
{
    private const int NOW = 1_763_632_800;

    private MockClock $clock;

    private Memory $memory;

    private InMemoryConsoleKeyStore $store;

    private RecordingLogger $logger;

    private VerifyingKey $current;

    private VerifyingKey $next;

    private ?IdempotencyGuard $guard = null;

    protected function setUp(): void
    {
        $this->clock = new MockClock('@'.self::NOW);
        $this->memory = new Memory();
        $this->store = new InMemoryConsoleKeyStore();
        $this->logger = new RecordingLogger();
        $this->current = Keys::console()->verifyingKey();
        $this->next = Keys::next()->verifyingKey();
    }

    public function testTheKeysOfARotation(): void
    {
        $keys = new ConsoleKeys($this->current, $this->next);

        self::assertTrue($keys->isCurrent($this->current));
        self::assertFalse($keys->isCurrent($this->next));
        self::assertTrue($keys->isNext($this->next));
        self::assertFalse($keys->isNext($this->current));
        self::assertNull(new ConsoleKeys($this->current, $this->current)->next, 'A next key equal to the current one is no next key.');
        self::assertFalse(new ConsoleKeys($this->current)->isNext($this->current));

        self::assertSame($this->current, $keys->signer('m', Keys::console()->sign('m')));
        self::assertSame($this->next, $keys->signer('m', Keys::next()->sign('m')));
        self::assertNull($keys->signer('m', Keys::site()->sign('m')));
        self::assertNull(new ConsoleKeys($this->current)->signer('m', Keys::next()->sign('m')));

        $other = SigningKey::generate()->verifyingKey();
        self::assertTrue($keys->staging($other)->isNext($other), 'Staging replaces another next key.');
        self::assertTrue($keys->staging($other)->isCurrent($this->current));

        $promoted = $keys->retiring($this->current);
        self::assertTrue($promoted->isCurrent($this->next));
        self::assertNull($promoted->next);
        $dropped = $keys->retiring($this->next);
        self::assertTrue($dropped->isCurrent($this->current));
        self::assertNull($dropped->next);
        self::assertSame($keys, $keys->retiring($other), 'A key not held changes nothing.');

        $this->expectExceptionObject(InvalidValue::because('The only key of the console cannot be retired.'));
        $promoted->retiring($this->next);
    }

    public function testTheKeysTravelAsJson(): void
    {
        $keys = new ConsoleKeys($this->current, $this->next);

        self::assertSame('{"current":"'.$this->current->toBase64().'","next":"'.$this->next->toBase64().'"}', $keys->toJson());
        self::assertSame('{"current":"'.$this->current->toBase64().'","next":null}', new ConsoleKeys($this->current)->toJson());
        self::assertSame($keys->toJson(), ConsoleKeys::fromJson($keys->toJson())->toJson());
        self::assertNull(ConsoleKeys::fromJson('{"current":"'.$this->current->toBase64().'","next":""}')->next);

        foreach (['{"current":"x"}', '{"next":"'.$this->next->toBase64().'"}', '{"current":"'.$this->current->toBase64().'","next":"x"}', '[]'] as $json) {
            try {
                ConsoleKeys::fromJson($json);
                self::fail('Malformed: '.$json);
            } catch (MalformedDocument) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTheRingStartsFromTheConfiguration(): void
    {
        $ring = new ConsoleKeyRing($this->current, $this->next, $this->store);

        self::assertTrue($ring->keys()->isCurrent($this->current));
        self::assertTrue($ring->keys()->isNext($this->next));
        self::assertNull($this->store->state, 'Reading writes nothing.');
    }

    public function testTheRingKeepsWhatARotationChangedWhileTheConfigurationStays(): void
    {
        $ring = new ConsoleKeyRing($this->current, null, $this->store);
        $ring->stage($this->next);

        self::assertNotNull($this->store->state);
        self::assertStringStartsWith(hash('sha256', new ConsoleKeys($this->current)->toJson())."\n", $this->store->state);
        $again = new ConsoleKeyRing($this->current, null, $this->store);
        self::assertTrue($again->keys()->isNext($this->next), 'Another process reads the staged key.');

        $promoted = $again->retire($this->current);
        self::assertTrue($promoted->isCurrent($this->next));
        self::assertTrue(new ConsoleKeyRing($this->current, null, $this->store)->keys()->isCurrent($this->next));
        self::assertNull(new ConsoleKeyRing($this->current, null, $this->store)->keys()->next);
    }

    public function testANewConfigurationWinsOverTheStore(): void
    {
        new ConsoleKeyRing($this->current, null, $this->store)->stage($this->next);
        $deployed = new ConsoleKeyRing($this->next, null, $this->store);

        self::assertTrue($deployed->keys()->isCurrent($this->next));
        self::assertNull($deployed->keys()->next);

        $withNext = new ConsoleKeyRing($this->current, $this->next, $this->store);
        self::assertTrue($withNext->keys()->isNext($this->next), 'The configured next key counts too.');
    }

    public function testAnUnreadableStoreFallsBackToTheConfiguration(): void
    {
        $fingerprint = hash('sha256', new ConsoleKeys($this->current)->toJson());
        $this->store->state = $fingerprint."\n{broken";
        $ring = new ConsoleKeyRing($this->current, null, $this->store, $this->logger);

        $keys = $ring->keys();
        self::assertTrue($keys->isCurrent($this->current));
        self::assertNull($keys->next);
        self::assertCount(1, $this->logger->lines);
        self::assertStringStartsWith('warning: The stored console keys are unreadable; the configured keys apply: ', $this->logger->lines[0]);

        $this->store->state = $fingerprint;
        self::assertTrue(new ConsoleKeyRing($this->current, null, $this->store)->keys()->isCurrent($this->current), 'A state without keys is unreadable too.');
    }

    public function testTheVerifierAcceptsEitherKeyAndNamesTheSigner(): void
    {
        $verifier = $this->verifier(new ConsoleKeyRing($this->current, $this->next, $this->store));

        $byCurrent = $this->verify($verifier, Keys::console());
        self::assertSame($this->current, $byCurrent->signer);
        self::assertSame(32, \strlen($byCurrent->nonce));
        self::assertSame($this->next, $this->verify($verifier, Keys::next())->signer);

        $this->expectExceptionObject(SignatureRefused::because('The signature does not verify with a key of the console.'));
        $this->verify($verifier, Keys::site());
    }

    public function testARetiredKeyIsRefused(): void
    {
        $ring = new ConsoleKeyRing($this->current, null, $this->store);
        $ring->stage($this->next);
        $ring->retire($this->current);

        self::assertTrue($this->verify($this->verifier($ring), Keys::next())->signer->equals($this->next));

        try {
            $this->verify($this->verifier($ring), Keys::console());
            self::fail('The retired key is refused.');
        } catch (SignatureRefused $signatureRefused) {
            self::assertSame('The signature does not verify with a key of the console.', $signatureRefused->getMessage());
        }

        self::assertCount(1, $this->memory->nonces, 'A refused request leaves no nonce.');
    }

    public function testAKeyIsStagedOnceByTheCurrentKey(): void
    {
        $ring = new ConsoleKeyRing($this->current, null, $this->store);
        $endpoints = $this->endpoints($ring);
        $body = $this->body('R:stage:shop', KeyOperation::Stage, $this->next);

        $first = $endpoints->keys($body, $this->current, 1000);
        self::assertTrue($first->succeeded);
        self::assertFalse($first->replayed);
        self::assertSame('The next key is staged.', $first->message);
        self::assertTrue($ring->keys()->isNext($this->next));
        self::assertSame(['notice: The console staged its next key '.$this->next->toBase64().'.'], $this->logger->lines);

        $replayed = $endpoints->keys($body, $this->current, 1000);
        self::assertTrue($replayed->replayed);
        self::assertSame('The next key is staged.', $replayed->message);
        self::assertCount(1, $this->logger->lines, 'A replay changes nothing.');

        self::assertSame('The key is already staged.', $endpoints->keys($this->body('R2', KeyOperation::Stage, $this->next), $this->current, 1000)->message);
        self::assertSame('The key is already the current key.', $endpoints->keys($this->body('R3', KeyOperation::Stage, $this->current), $this->current, 1000)->message);
    }

    public function testAStageSignedByTheNextKeyIsRefused(): void
    {
        $ring = new ConsoleKeyRing($this->current, $this->next, $this->store);
        $other = SigningKey::generate()->verifyingKey();

        $this->expectExceptionObject(KeyRefused::stageSignedByNext());

        try {
            $this->endpoints($ring)->keys($this->body('R', KeyOperation::Stage, $other), $this->next, 1000);
        } finally {
            self::assertTrue($ring->keys()->isNext($this->next));
            self::assertNull($this->store->state);
        }
    }

    public function testAKeyIsRetiredByTheKeyThatRemains(): void
    {
        $ring = new ConsoleKeyRing($this->current, $this->next, $this->store);
        $endpoints = $this->endpoints($ring);

        try {
            $endpoints->keys($this->body('R:retire:shop', KeyOperation::Retire, $this->current), $this->current, 1000);
            self::fail('The key that goes does not retire itself.');
        } catch (KeyRefused $keyRefused) {
            self::assertSame('A key is retired by a request signed with the key that remains.', $keyRefused->getMessage());
        }

        $retired = $endpoints->keys($this->body('R:retire:shop', KeyOperation::Retire, $this->current), $this->next, 1000);
        self::assertSame('The key is retired.', $retired->message);
        self::assertTrue($ring->keys()->isCurrent($this->next));
        self::assertSame(['notice: The console retired its key '.$this->current->toBase64().'.'], $this->logger->lines);
        self::assertTrue($endpoints->keys($this->body('R:retire:shop', KeyOperation::Retire, $this->current), $this->next, 1000)->replayed);
        self::assertSame('The key was not held.', $endpoints->keys($this->body('R4', KeyOperation::Retire, $this->current), $this->next, 1000)->message);

        $this->expectExceptionObject(KeyRefused::lastKey());
        $endpoints->keys($this->body('R5', KeyOperation::Retire, $this->next), $this->next, 1000);
    }

    public function testAStagedKeyIsDroppedByTheCurrentKey(): void
    {
        $ring = new ConsoleKeyRing($this->current, null, $this->store);
        $ring->stage($this->next);

        $endpoints = $this->endpoints($ring);

        try {
            $endpoints->keys($this->body('D', KeyOperation::Retire, $this->next), $this->next, 1000);
            self::fail('The staged key does not drop itself.');
        } catch (KeyRefused) {
            self::assertTrue($ring->keys()->isNext($this->next));
        }

        self::assertSame('The key is retired.', $endpoints->keys($this->body('D', KeyOperation::Retire, $this->next), $this->current, 1000)->message);
        self::assertNull($ring->keys()->next);
        self::assertTrue($ring->keys()->isCurrent($this->current));
    }

    public function testAnAgentWithoutAKeyRing(): void
    {
        $endpoints = new AgentEndpoints('shop', $this->memory, $this->memory, $this->guard(), $this->clock);

        $this->expectExceptionObject(ActionUnavailable::keys());
        $endpoints->keys($this->body('R', KeyOperation::Stage, $this->next), $this->current, 1000);
    }

    public function testAKeyChangeThatFailsIsAFailureWithoutChange(): void
    {
        $store = new class implements ConsoleKeyStore {
            #[Override]
            public function read(): ?string
            {
                return null;
            }

            #[Override]
            public function write(string $state): void
            {
                throw new RuntimeException('The pool is down.');
            }
        };
        $outcome = $this->endpoints(new ConsoleKeyRing($this->current, null, $store))->keys($this->body('F', KeyOperation::Stage, $this->next), $this->current, 1000);

        self::assertFalse($outcome->succeeded);
        self::assertSame('The site could not perform the request; see its logs.', $outcome->message);
    }

    public function testARetiredKeyAStoreDidNotKeepIsNeverReportedRetired(): void
    {
        $forgetful = new class implements ConsoleKeyStore {
            public ?string $state = null;

            #[Override]
            public function read(): ?string
            {
                return $this->state;
            }

            #[Override]
            public function write(string $state): void
            {
                // Accepted, then lost: a pool that evicts, a replica behind.
            }
        };
        $ring = new ConsoleKeyRing($this->current, $this->next, $forgetful);

        try {
            $ring->retire($this->current);
            self::fail('A change the store did not keep fails.');
        } catch (StateNotKept $stateNotKept) {
            self::assertSame('The console keys could not be kept.', $stateNotKept->getMessage());
        }

        try {
            $ring->stage(SigningKey::generate()->verifyingKey());
            self::fail('A staged key the store did not keep fails too.');
        } catch (StateNotKept) {
            self::assertTrue($ring->keys()->isNext($this->next));
        }

        $outcome = $this->endpoints($ring)->keys($this->body('G', KeyOperation::Retire, $this->current), $this->next, 1000);
        self::assertFalse($outcome->succeeded, 'The console learns that the key is still trusted.');
        self::assertTrue($ring->keys()->isCurrent($this->current));
    }

    /**
     * @param non-empty-string $json
     */
    #[DataProvider('malformedRequests')]
    public function testAMalformedKeyRequest(string $json, string $reason): void
    {
        try {
            KeyRequest::fromJson($json);
            self::fail('Malformed.');
        } catch (MalformedDocument $malformedDocument) {
            self::assertStringContainsString($reason, $malformedDocument->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedRequests(): iterable
    {
        $key = Keys::next()->verifyingKey()->toBase64();

        yield 'unknown operation' => ['{"id":"R","operation":"rotate","key":"'.$key.'"}', '"operation" is stage or retire.'];
        yield 'no operation' => ['{"id":"R","key":"'.$key.'"}', 'operation'];
        yield 'not a key' => ['{"id":"R","operation":"stage","key":"abc"}', 'A public key is the base64 of the 32 bytes'];
        yield 'no key' => ['{"id":"R","operation":"stage"}', 'key'];
        yield 'no id' => ['{"operation":"stage","key":"'.$key.'"}', 'id'];
        yield 'id too long' => ['{"id":"'.str_repeat('a', KeyRequest::ID_MAX_LENGTH + 1).'","operation":"stage","key":"'.$key.'"}', 'id'];
    }

    public function testAKeyRequestRoundTrips(): void
    {
        $request = new KeyRequest(str_repeat('a', KeyRequest::ID_MAX_LENGTH), KeyOperation::Retire, $this->current);
        $read = KeyRequest::fromJson($request->toJson());

        self::assertSame(str_repeat('a', KeyRequest::ID_MAX_LENGTH), $read->id);
        self::assertSame(KeyOperation::Retire, $read->operation);
        self::assertTrue($read->key->equals($this->current));
        self::assertFalse($read->key->equals($this->next));

        $this->expectException(MalformedDocument::class);
        KeyRequest::fromJson($request->toJson(), 10);
    }

    public function testTheLengthsOfTheFieldsOfAKeyDocumentAreBounded(): void
    {
        $key = $this->current->toBase64();
        $tooLong = str_repeat('A', 65);
        $atTheLimit = str_repeat('A', 64);
        $keysReason = 'A public key is the base64 of the 32 bytes of an Ed25519 key.';
        $lengthReason = 'is a text of 1 to 64 characters';

        self::assertSame($keysReason, $this->reasonOf(static fn (): ConsoleKeys => ConsoleKeys::fromJson('{"current":"'.$atTheLimit.'"}')));
        self::assertStringContainsString($lengthReason, $this->reasonOf(static fn (): ConsoleKeys => ConsoleKeys::fromJson('{"current":"'.$tooLong.'"}')));
        self::assertSame($keysReason, $this->reasonOf(static fn (): ConsoleKeys => ConsoleKeys::fromJson('{"current":"'.$key.'","next":"'.$atTheLimit.'"}')));
        self::assertStringContainsString($lengthReason, $this->reasonOf(static fn (): ConsoleKeys => ConsoleKeys::fromJson('{"current":"'.$key.'","next":"'.$tooLong.'"}')));

        $request = static fn (string $operation, string $key): string => '{"id":"a","operation":"'.$operation.'","key":"'.$key.'"}';

        self::assertSame($keysReason, $this->reasonOf(static fn (): KeyRequest => KeyRequest::fromJson($request('stage', $atTheLimit))));
        self::assertStringContainsString($lengthReason, $this->reasonOf(static fn (): KeyRequest => KeyRequest::fromJson($request('stage', $tooLong))));
        self::assertSame('"operation" is stage or retire.', $this->reasonOf(static fn (): KeyRequest => KeyRequest::fromJson($request(str_repeat('s', 16), $key))));
        self::assertStringContainsString('is a text of 1 to 16 characters', $this->reasonOf(static fn (): KeyRequest => KeyRequest::fromJson($request(str_repeat('s', 17), $key))));
    }

    public function testAStoredStateWithTrailingLinesIsUnreadable(): void
    {
        $configured = new ConsoleKeys($this->current);
        $stored = new ConsoleKeys($this->current, $this->next);
        $this->store->state = hash('sha256', $configured->toJson())."\n".$stored->toJson()."\nextra";

        $keys = new ConsoleKeyRing($this->current, null, $this->store, $this->logger)->keys();

        self::assertNull($keys->next, 'Only the second line is the keys: the rest makes them unreadable, and the configuration applies.');
        self::assertCount(1, $this->logger->lines);
    }

    public function testTheLogNamesWhyTheStoredKeysAreUnreadable(): void
    {
        $this->store->state = hash('sha256', new ConsoleKeys($this->current)->toJson())."\n[]";

        new ConsoleKeyRing($this->current, null, $this->store, $this->logger)->keys();

        self::assertSame(['warning: The stored console keys are unreadable; the configured keys apply: '.$this->malformedReason('[]')], $this->logger->lines);
    }

    /**
     * @param callable(): mixed $read
     */
    private function reasonOf(callable $read): string
    {
        try {
            $read();
        } catch (MalformedDocument $malformedDocument) {
            return $malformedDocument->getMessage();
        }

        self::fail('Malformed.');
    }

    private function malformedReason(string $json): string
    {
        return $this->reasonOf(static fn (): ConsoleKeys => ConsoleKeys::fromJson($json));
    }

    private function verifier(ConsoleKeyRing $ring): RequestVerifier
    {
        return new RequestVerifier('shop', $ring, $this->memory, $this->clock);
    }

    private function verify(RequestVerifier $verifier, SigningKey $key): VerifiedRequest
    {
        $nonce = Protocol::nonce();

        return $verifier->verify('keys', 'shop', (string) self::NOW, $nonce, $key->sign(Protocol::requestMessage('shop', 'keys', self::NOW, $nonce, '{}')), '{}');
    }

    private function endpoints(ConsoleKeyRing $ring): AgentEndpoints
    {
        return new AgentEndpoints('shop', $this->memory, $this->memory, $this->guard(), $this->clock, logger: $this->logger, consoleKeys: $ring);
    }

    private function guard(): IdempotencyGuard
    {
        return $this->guard ??= new IdempotencyGuard(new CacheIdempotencyStore(new ArrayAdapter(clock: $this->clock), new LockFactory(new InMemoryStore()), $this->clock), $this->clock);
    }

    private function body(string $id, KeyOperation $operation, VerifyingKey $key): string
    {
        return new KeyRequest($id, $operation, $key)->toJson();
    }
}
