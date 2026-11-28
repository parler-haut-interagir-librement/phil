<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Application;

use Phil\Idempotency\Exception\IdempotencyKeyReused;
use Phil\Idempotency\Exception\RequestInProgress;
use Phil\Idempotency\IdempotencyGuard;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\Store\CacheIdempotencyStore;
use Phil\SitesConsoleBundle\Application\Agent\AgentEndpoints;
use Phil\SitesConsoleBundle\Application\Agent\ConsoleKeyRing;
use Phil\SitesConsoleBundle\Application\Agent\RequestVerifier;
use Phil\SitesConsoleBundle\Application\Exception\ActionUnavailable;
use Phil\SitesConsoleBundle\Application\Exception\ConfigurationRefused;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;
use Phil\SitesConsoleBundle\Domain\Exception\SignatureRefused;
use Phil\SitesConsoleBundle\Domain\Signature\Protocol;
use Phil\SitesConsoleBundle\Domain\Status\HealthState;
use Phil\SitesConsoleBundle\Domain\ValueObject\HealthSummary;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Infrastructure\Memory\InMemoryConsoleKeyStore;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Keys;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Memory;
use Phil\SitesConsoleBundle\Tests\Unit\Support\RecordingLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(RequestVerifier::class)]
#[CoversClass(AgentEndpoints::class)]
#[CoversClass(SignatureRefused::class)]
#[CoversClass(ActionUnavailable::class)]
#[CoversClass(ConfigurationRefused::class)]
final class AgentTest extends TestCase
{
    private const int NOW = 1_763_632_800;

    private MockClock $clock;

    private Memory $memory;

    private IdempotencyGuard $guard;

    protected function setUp(): void
    {
        $this->clock = new MockClock('@'.self::NOW);
        $this->memory = new Memory(['phil/url' => '0.1.4', 'symfony/http-kernel' => 'v8.2.0', 'other/lib' => '1.0']);
        $this->guard = new IdempotencyGuard(new CacheIdempotencyStore(new ArrayAdapter(clock: $this->clock), new LockFactory(new InMemoryStore()), $this->clock), $this->clock);
    }

    public function testASignedRequestIsAcceptedOnceAndItsNonceKeptTwiceTheSkew(): void
    {
        $verifier = $this->verifier(maxSkew: 120);
        $nonce = Protocol::nonce();
        $signature = Keys::console()->sign(Protocol::requestMessage('shop', 'status', self::NOW + 120, $nonce, 'body'));

        self::assertSame($nonce, $verifier->verify('status', 'shop', (string) (self::NOW + 120), $nonce, $signature, 'body')->nonce);
        self::assertSame([$nonce => 240], $this->memory->nonces);

        $this->expectExceptionObject(SignatureRefused::because('The nonce was seen before.'));
        $verifier->verify('status', 'shop', (string) (self::NOW + 120), $nonce, $signature, 'body');
    }

    public function testADateAtTheEdgeOfThePastWindow(): void
    {
        $nonce = Protocol::nonce();
        $date = self::NOW - 300;

        self::assertSame($nonce, $this->verifier()->verify('update', 'shop', (string) $date, $nonce, Keys::console()->sign(Protocol::requestMessage('shop', 'update', $date, $nonce, '')), '')->nonce);
    }

    /**
     * @param array{0: ?string, 1: ?string, 2: ?string, 3: ?string} $headers
     */
    #[DataProvider('refusals')]
    public function testARequestIsRefused(array $headers, string $reason): void
    {
        $verifier = $this->verifier();

        try {
            $verifier->verify('status', ...$headers, body: 'body');
            self::fail('The request is refused.');
        } catch (SignatureRefused $signatureRefused) {
            self::assertSame($reason, $signatureRefused->getMessage());
        }

        self::assertSame([], $this->memory->nonces, 'A refused request leaves no nonce.');
    }

    /**
     * @return iterable<string, array{array{0: ?string, 1: ?string, 2: ?string, 3: ?string}, string}>
     */
    public static function refusals(): iterable
    {
        $nonce = str_repeat('n', 32);
        $sign = static fn (string $site, string $action, int $date, string $body = 'body'): string => Keys::console()->sign(Protocol::requestMessage($site, $action, $date, $nonce, $body));
        $good = $sign('shop', 'status', self::NOW);

        yield 'no site' => [[null, (string) self::NOW, $nonce, $good], 'A header of the signature is missing.'];
        yield 'no date' => [['shop', null, $nonce, $good], 'A header of the signature is missing.'];
        yield 'no nonce' => [['shop', (string) self::NOW, null, $good], 'A header of the signature is missing.'];
        yield 'no signature' => [['shop', (string) self::NOW, $nonce, null], 'A header of the signature is missing.'];
        yield 'another site' => [['blog', (string) self::NOW, $nonce, $sign('blog', 'status', self::NOW)], 'The request is meant for another site.'];
        yield 'too old' => [['shop', (string) (self::NOW - 301), $nonce, $sign('shop', 'status', self::NOW - 301)], 'The date of the request is outside the allowed window.'];
        yield 'too new' => [['shop', (string) (self::NOW + 301), $nonce, $sign('shop', 'status', self::NOW + 301)], 'The date of the request is outside the allowed window.'];
        yield 'not a number' => [['shop', '1e9', $nonce, $good], 'The date of the request is outside the allowed window.'];
        yield 'trailing newline' => [['shop', self::NOW."\n", $nonce, $good], 'The date of the request is outside the allowed window.'];
        yield 'leading sign' => [['shop', '+'.self::NOW, $nonce, $good], 'The date of the request is outside the allowed window.'];
        yield 'too many digits' => [['shop', '0'.str_repeat('0', 2).self::NOW, $nonce, $good], 'The date of the request is outside the allowed window.'];
        yield 'bad nonce' => [['shop', (string) self::NOW, 'short', $good], 'The nonce is malformed.'];
        yield 'another action' => [['shop', (string) self::NOW, $nonce, $sign('shop', 'update', self::NOW)], 'The signature does not verify with a key of the console.'];
        yield 'another body' => [['shop', (string) self::NOW, $nonce, $sign('shop', 'status', self::NOW, 'other')], 'The signature does not verify with a key of the console.'];
        yield 'another key' => [['shop', (string) self::NOW, $nonce, Keys::site()->sign(Protocol::requestMessage('shop', 'status', self::NOW, $nonce, 'body'))], 'The signature does not verify with a key of the console.'];
    }

    public function testTheSkewHasBounds(): void
    {
        self::assertInstanceOf(RequestVerifier::class, $this->verifier(maxSkew: 1));
        self::assertInstanceOf(RequestVerifier::class, $this->verifier(maxSkew: 3600));

        foreach ([0, 3601] as $skew) {
            try {
                $this->verifier(maxSkew: $skew);
                self::fail('The skew is bounded.');
            } catch (InvalidValue $invalid) {
                self::assertSame('The allowed clock skew is 1 to 3600 seconds.', $invalid->getMessage());
            }
        }
    }

    public function testTheStatusReportsTheChosenPackagesAndTheHealth(): void
    {
        $this->memory->health = new HealthSummary(HealthState::Degraded);
        $status = $this->endpoints(packages: ['phil/*', 'symfony/http-kernel'])->status();

        self::assertSame('shop', $status->site);
        self::assertSame(['phil/url' => '0.1.4', 'symfony/http-kernel' => 'v8.2.0'], $status->packages);
        self::assertSame(\PHP_VERSION, $status->php);
        self::assertSame(Kernel::VERSION, $status->symfony);
        self::assertSame(HealthState::Degraded, $status->health->state);
        self::assertSame(self::NOW, $status->generatedAt->getTimestamp());
        self::assertSame(['other/lib', 'phil/url', 'symfony/http-kernel'], array_keys($this->endpoints()->status()->packages));
    }

    public function testAPushIsAppliedOnceAndReplayedAfter(): void
    {
        $endpoints = $this->endpoints(keys: ['mail.*'], applier: true);
        $body = '{"id":"PUSH1","settings":{"mail.footer":"Hi","mail.from":"a"}}';

        $first = $endpoints->configure($body, 1000);
        self::assertTrue($first->succeeded);
        self::assertFalse($first->replayed);
        self::assertSame('2 settings applied.', $first->message);

        $second = $endpoints->configure($body, 1000);
        self::assertTrue($second->succeeded);
        self::assertTrue($second->replayed);
        self::assertCount(1, $this->memory->applied);
        self::assertSame(['mail.footer' => 'Hi', 'mail.from' => 'a'], $this->memory->applied[0]->values);
    }

    public function testAPushIdReusedWithOtherSettingsIsRefused(): void
    {
        $endpoints = $this->endpoints(keys: ['*'], applier: true);
        $endpoints->configure('{"id":"PUSH1","settings":{"a":1}}', 1000);

        $this->expectException(IdempotencyKeyReused::class);
        $endpoints->configure('{"id":"PUSH1","settings":{"a":2}}', 1000);
    }

    public function testAnUpdateIdDoesNotCollideWithAPushId(): void
    {
        $endpoints = $this->endpoints(keys: ['*'], applier: true, runner: true);
        $endpoints->configure('{"id":"SAME","settings":{"a":1}}', 1000);

        self::assertFalse($endpoints->update('{"id":"SAME","package":"phil/url","version":"0.1.5"}', 1000)->replayed);
    }

    public function testAPushStillRunningIsToldToComeBack(): void
    {
        $body = '{"id":"PUSH1","settings":{"a":1}}';
        $this->guard->begin(IdempotencyScope::of(AgentEndpoints::SCOPE_CONFIGURATION), IdempotencyKey::fromString('PUSH1'), PayloadFingerprint::ofParts(AgentEndpoints::SCOPE_CONFIGURATION, $body));

        $this->expectException(RequestInProgress::class);
        $this->endpoints(keys: ['*'], applier: true)->configure($body, 1000);
    }

    public function testAKeyNotAllowedRefusesThePush(): void
    {
        try {
            $this->endpoints(keys: ['mail.*'], applier: true)->configure('{"id":"P","settings":{"mail.footer":"x","cache.ttl":1,"debug":true}}', 1000);
            self::fail('The push is refused.');
        } catch (ConfigurationRefused $configurationRefused) {
            self::assertSame('This site does not accept the keys cache.ttl, debug.', $configurationRefused->getMessage());
        }

        self::assertSame([], $this->memory->applied);
    }

    public function testNoKeyIsAllowedByDefault(): void
    {
        $this->expectExceptionObject(ConfigurationRefused::keys(['a']));
        $this->endpoints(applier: true)->configure('{"id":"P","settings":{"a":1}}', 1000);
    }

    public function testWithoutAnApplierThereIsNoPush(): void
    {
        $this->expectExceptionObject(ActionUnavailable::configuration());
        $this->endpoints(keys: ['*'])->configure('{"id":"P","settings":{"a":1}}', 1000);
    }

    public function testWithoutARunnerThereIsNoUpdate(): void
    {
        $this->expectExceptionObject(ActionUnavailable::update());
        $this->endpoints()->update('{"id":"P:shop","package":"phil/url","version":"1"}', 1000);
    }

    public function testAMalformedPushIsRefused(): void
    {
        $this->expectExceptionObject(MalformedDocument::because('The document is larger than 5 bytes.'));
        $this->endpoints(keys: ['*'], applier: true)->configure('{"id":"P","settings":{"a":1}}', 5);
    }

    public function testAMalformedUpdateIsRefused(): void
    {
        $this->expectExceptionObject(MalformedDocument::because('The document is larger than 5 bytes.'));
        $this->endpoints(runner: true)->update('{"id":"P:shop","package":"phil/url","version":"1"}', 5);
    }

    public function testAFailingApplierIsLoggedAndReportedAndMayRunAgain(): void
    {
        $logger = new RecordingLogger();
        $endpoints = $this->endpoints(keys: ['*'], applier: true, logger: $logger);
        $body = '{"id":"PUSH1","settings":{"a":1}}';
        $this->memory->fail = true;

        $failed = $endpoints->configure($body, 1000);
        self::assertFalse($failed->succeeded);
        self::assertSame('The site could not perform the request; see its logs.', $failed->message);
        self::assertSame(['error: The site could not perform the request PUSH1 of the console.'], $logger->lines);

        $this->memory->fail = false;
        self::assertTrue($endpoints->configure($body, 1000)->succeeded);
        self::assertTrue($this->endpoints(keys: ['*'], applier: true)->configure($body, 1000)->replayed);
    }

    public function testAnUpdateRunsOnceAndItsFailureIsKept(): void
    {
        $endpoints = $this->endpoints(runner: true);
        $body = '{"id":"PLAN:shop","package":"phil/url","version":"0.1.5"}';

        self::assertSame('Deployed 0.1.5', $endpoints->update($body, 1000)->message);
        self::assertTrue($endpoints->update($body, 1000)->replayed);
        self::assertSame(['phil/url 0.1.5'], $this->memory->updates);

        $this->memory->outcome = Outcome::failed('Tests failed');
        $failed = $endpoints->update('{"id":"PLAN2:shop","package":"phil/url","version":"0.1.6"}', 1000);
        self::assertFalse($failed->succeeded);
        self::assertSame('Tests failed', $failed->message);
    }

    public function testARunnerThatThrowsGivesAFailureWithoutALogger(): void
    {
        $this->memory->fail = true;

        self::assertSame('The site could not perform the request; see its logs.', $this->endpoints(runner: true)->update('{"id":"X:shop","package":"phil/url","version":"1"}', 1000)->message);
    }

    private function verifier(int $maxSkew = 300): RequestVerifier
    {
        return new RequestVerifier('shop', new ConsoleKeyRing(Keys::console()->verifyingKey(), null, new InMemoryConsoleKeyStore()), $this->memory, $this->clock, $maxSkew);
    }

    /**
     * @param list<string> $packages
     * @param list<string> $keys
     */
    private function endpoints(array $packages = [], array $keys = [], bool $applier = false, bool $runner = false, ?RecordingLogger $logger = null): AgentEndpoints
    {
        return new AgentEndpoints('shop', $this->memory, $this->memory, $this->guard, $this->clock, $packages, $keys, $applier ? $this->memory : null, $runner ? $this->memory : null, $logger);
    }
}
