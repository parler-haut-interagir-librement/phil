<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Tests\Unit\Application;

use Phil\IdentityProviderGoogle\Application\SignInAttempt;
use Phil\IdentityProviderGoogle\Application\SignInAttempts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

#[CoversClass(SignInAttempts::class)]
#[CoversClass(SignInAttempt::class)]
final class SignInAttemptsTest extends TestCase
{
    public function testAnAttemptIsTakenOnce(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('other', 'kept');

        $attempts = new SignInAttempts();
        $attempts->save($session, 'state', new SignInAttempt('nonce', 'verifier', 'https://example.com/cb', 42));

        self::assertEquals(new SignInAttempt('nonce', 'verifier', 'https://example.com/cb', 42), $attempts->take($session, 'state'));
        self::assertNull($attempts->take($session, 'state'));
        self::assertSame(['other' => 'kept'], $session->all());
    }

    public function testAttemptsAreCappedTheOldestDroppedFirst(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $attempts = new SignInAttempts();

        for ($i = 1; $i <= 6; ++$i) {
            $attempts->save($session, 's'.$i, new SignInAttempt('n'.$i, 'v', 'r', $i));
        }

        self::assertSame(['s2', 's3', 's4', 's5', 's6'], array_map(static fn (string $key): string => substr($key, \strlen(SignInAttempts::PREFIX)), array_keys($session->all())));
        self::assertNull($attempts->take($session, 's1'));
        self::assertSame('n2', $attempts->take($session, 's2')?->nonce);
    }

    public function testAnotherStateTakesNothing(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $attempts = new SignInAttempts();
        $attempts->save($session, 'state', new SignInAttempt('n', 'v', 'r', 1));

        self::assertNull($attempts->take($session, 'stat'));
        self::assertNull($attempts->take($session, 'state2'));
        self::assertCount(1, $session->all());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function corrupted(): iterable
    {
        $attempt = ['nonce' => 'n', 'code_verifier' => 'v', 'redirect_uri' => 'r', 'started_at' => 1];

        yield 'not an array' => ['x'];
        yield 'no nonce' => [['nonce' => null] + $attempt];
        yield 'no verifier' => [['code_verifier' => 1] + $attempt];
        yield 'no redirect' => [['redirect_uri' => false] + $attempt];
        yield 'no start' => [['started_at' => '1'] + $attempt];
    }

    #[DataProvider('corrupted')]
    public function testWhatIsNotAnAttemptIsDropped(mixed $held): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set(SignInAttempts::PREFIX.'state', $held);

        self::assertNull(new SignInAttempts()->take($session, 'state'));
        self::assertSame([], $session->all());
    }

    public function testOtherEntriesOfTheSessionAreNeverDroppedToMakeRoom(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('other', 'kept');
        $session->set('5', 'numeric');

        $attempts = new SignInAttempts();

        for ($i = 1; $i <= 6; ++$i) {
            $attempts->save($session, 's'.$i, new SignInAttempt('n'.$i, 'v', 'r', $i));
        }

        self::assertSame('kept', $session->get('other'));
        self::assertSame('numeric', $session->get('5'));
        self::assertNull($attempts->take($session, 's1'));
        self::assertSame('n2', $attempts->take($session, 's2')?->nonce);
    }
}
