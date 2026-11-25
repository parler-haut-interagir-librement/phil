<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Infrastructure;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Override;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Infrastructure\Security\PasswordCheckSemaphore;
use Phil\IdentityProviderBundle\Infrastructure\Security\SemaphorePasswordHasher;
use Phil\IdentityProviderBundle\Infrastructure\Security\SemaphorePasswordHasherFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\PasswordHasher\Hasher\MessageDigestPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\PlaintextPasswordHasher;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(PasswordCheckSemaphore::class)]
#[CoversClass(SemaphorePasswordHasher::class)]
#[CoversClass(SemaphorePasswordHasherFactory::class)]
final class PasswordSemaphoreTest extends TestCase
{
    public function testAHashRunsInAFreeSlotAndGivesItBack(): void
    {
        $locks = new SpyLockFactory(new InMemoryStore());
        $semaphore = new PasswordCheckSemaphore($locks, 1, 0.0);

        self::assertSame('first', $semaphore->run(static fn (): string => 'first'));
        self::assertSame('second', $semaphore->run(static fn (): string => 'second'));
        self::assertCount(2, $locks->locks());
        self::assertTrue($locks->createLock('phil_identity_provider.password_check.0')->acquire());
    }

    public function testASlotIsGivenBackWhenTheHashFails(): void
    {
        $locks = new SpyLockFactory(new InMemoryStore());
        $semaphore = new PasswordCheckSemaphore($locks, 1, 0.0);

        try {
            $semaphore->run($this->failing());
            self::fail('The failure goes through.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('failed', $runtimeException->getMessage());
        }

        self::assertTrue($locks->createLock('phil_identity_provider.password_check.0')->acquire());
    }

    public function testEverySlotTakenRefusesWithoutCountingAsAWrongPassword(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $held = [$locks->createLock('phil_identity_provider.password_check.0'), $locks->createLock('phil_identity_provider.password_check.1')];
        $held[0]->acquire();
        $held[1]->acquire();
        $semaphore = new PasswordCheckSemaphore($locks, 2, 0.02);
        $ran = false;
        $start = hrtime(true);

        try {
            $semaphore->run(static function () use (&$ran): void {
                $ran = true;
            });
            self::fail('Every slot is taken.');
        } catch (CustomUserMessageAuthenticationException $customUserMessageAuthenticationException) {
            self::assertNotInstanceOf(BadCredentialsException::class, $customUserMessageAuthenticationException);
            self::assertSame('password.busy', $customUserMessageAuthenticationException->getMessageKey());
        }

        self::assertFalse($ran);
        self::assertGreaterThanOrEqual(20_000_000, hrtime(true) - $start);
        self::assertLessThan(1_000_000_000, hrtime(true) - $start);
    }

    public function testTheLastSlotFreeIsFound(): void
    {
        $locks = new LockFactory(new InMemoryStore());

        $held = [];

        for ($i = 0; $i < 7; ++$i) {
            $held[$i] = $locks->createLock('phil_identity_provider.password_check.'.$i);
            self::assertTrue($held[$i]->acquire());
        }

        $semaphore = new PasswordCheckSemaphore($locks, 8, 0.0);

        for ($round = 0; $round < 20; ++$round) {
            self::assertSame($round, $semaphore->run(static fn (): int => $round));
        }
    }

    public function testTheWaitIsCountedToTheMicrosecondAndPausesAreShort(): void
    {
        $locks = new SpyLockFactory(new InMemoryStore());
        $held = $locks->createLock('phil_identity_provider.password_check.0');
        $held->acquire();

        $clock = new StepClock();
        $locks->forget();

        try {
            new PasswordCheckSemaphore($locks, 1, 0.012, $clock)->run(static fn (): null => null);
            self::fail('The slot is taken.');
        } catch (CustomUserMessageAuthenticationException) {
        }

        self::assertSame([0.005, 0.005, 0.002], $clock->sleeps);
        self::assertSame(12_000, $clock->elapsed());
        self::assertCount(4, $locks->names());
    }

    public function testAWaitOfOneSecondLastsOneSecond(): void
    {
        $locks = new SpyLockFactory(new InMemoryStore());
        $held = $locks->createLock('phil_identity_provider.password_check.0');
        $held->acquire();

        $clock = new StepClock();

        try {
            new PasswordCheckSemaphore($locks, 1, 1.0, $clock)->run(static fn (): null => null);
            self::fail('The slot is taken.');
        } catch (CustomUserMessageAuthenticationException) {
        }

        self::assertCount(200, $clock->sleeps);
        self::assertSame(1_000_000, $clock->elapsed());
    }

    /**
     * @return iterable<string, array{float, float}>
     */
    public static function tinyWaits(): iterable
    {
        yield 'rounded down' => [0.000_001_2, 0.000_001];
        yield 'rounded up' => [0.000_001_8, 0.000_002];
    }

    #[DataProvider('tinyWaits')]
    public function testTheWaitIsRoundedToTheNearestMicrosecond(float $wait, float $slept): void
    {
        $locks = new SpyLockFactory(new InMemoryStore());
        $held = $locks->createLock('phil_identity_provider.password_check.0');
        $held->acquire();

        $clock = new StepClock();

        try {
            new PasswordCheckSemaphore($locks, 1, $wait, $clock)->run(static fn (): null => null);
            self::fail('The slot is taken.');
        } catch (CustomUserMessageAuthenticationException) {
        }

        self::assertSame([$slept], $clock->sleeps);
    }

    public function testWithoutWaitEverySlotIsTriedOnceAndNoneBeyond(): void
    {
        $locks = new SpyLockFactory(new InMemoryStore());
        $held = [];

        foreach ([0, 1, 2] as $slot) {
            $held[$slot] = $locks->createLock('phil_identity_provider.password_check.'.$slot);
            $held[$slot]->acquire();
        }

        $locks->forget();
        $clock = new StepClock();
        $semaphore = new PasswordCheckSemaphore($locks, 3, 0.0, $clock);

        for ($round = 0; $round < 40; ++$round) {
            try {
                $semaphore->run(static fn (): null => null);
                self::fail('Every slot is taken.');
            } catch (CustomUserMessageAuthenticationException) {
            }
        }

        self::assertSame([], $clock->sleeps);
        self::assertCount(120, $locks->names());
        $starts = [];

        foreach (array_chunk($locks->names(), 3) as $round) {
            $starts[$round[0]] = true;
        }

        self::assertGreaterThan(1, \count($starts), 'The round over the slots does not always start at the same one.');
        self::assertSame(['phil_identity_provider.password_check.0', 'phil_identity_provider.password_check.1', 'phil_identity_provider.password_check.2'], $this->sorted($locks->names()));
    }

    public function testTheHasherTakesASlotToHashAndToVerifyOnly(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $hasher = new SemaphorePasswordHasher($this->plain(), new PasswordCheckSemaphore($locks, 1, 0.0));
        $hash = $hasher->hash('secret');

        self::assertSame('plain:secret', $hash);
        self::assertTrue($hasher->verify($hash, 'secret'));
        self::assertFalse($hasher->verify($hash, 'other'));
        self::assertSame('plain:secret', $hasher->hash('secret', 'ignored'));
        self::assertTrue($hasher->verify($hash, 'secret', 'ignored'));
        self::assertFalse($hasher->needsRehash($hash));

        $held = $locks->createLock('phil_identity_provider.password_check.0');
        $held->acquire();
        self::assertFalse($hasher->needsRehash($hash));
        $this->expectException(CustomUserMessageAuthenticationException::class);
        $hasher->verify($hash, 'secret');
    }

    public function testTheFactoryWrapsTheAccountsOnly(): void
    {
        $semaphore = new PasswordCheckSemaphore(new LockFactory(new InMemoryStore()), 1, 0.0);
        $factory = new SemaphorePasswordHasherFactory(new PasswordHasherFactory([
            AccountUser::class => $this->plain(),
            InMemoryUser::class => $this->plain(),
            'plaintext' => new PlaintextPasswordHasher(),
            'legacy' => new MessageDigestPasswordHasher(),
        ]), $semaphore);
        $account = Account::register('01JCZ6Q7Y3W0000000000000AA', 'ada@example.com', new DateTimeImmutable('2026-11-24T12:00:00Z'));

        self::assertInstanceOf(SemaphorePasswordHasher::class, $factory->getPasswordHasher(AccountUser::class));
        self::assertInstanceOf(SemaphorePasswordHasher::class, $factory->getPasswordHasher(new AccountUser($account)));
        self::assertNotInstanceOf(SemaphorePasswordHasher::class, $factory->getPasswordHasher(new InMemoryUser('ada', 'secret')));
        self::assertNotInstanceOf(SemaphorePasswordHasher::class, $factory->getPasswordHasher(InMemoryUser::class));
        self::assertInstanceOf(MessageDigestPasswordHasher::class, $factory->getPasswordHasher('legacy'));
    }

    public function testASaltedHasherOfTheAccountsKeepsItsSalt(): void
    {
        $semaphore = new PasswordCheckSemaphore(new LockFactory(new InMemoryStore()), 1, 0.0);
        $factory = new SemaphorePasswordHasherFactory(new PasswordHasherFactory([AccountUser::class => new PlaintextPasswordHasher()]), $semaphore);
        $hasher = $factory->getPasswordHasher(AccountUser::class);

        self::assertInstanceOf(SemaphorePasswordHasher::class, $hasher);
        self::assertSame('secret{pepper}', $hasher->hash('secret', 'pepper'));
        self::assertTrue($hasher->verify('secret{pepper}', 'secret', 'pepper'));
        self::assertFalse($hasher->verify('secret{pepper}', 'secret', 'other'));
        self::assertSame('secret', $hasher->hash('secret'));
    }

    /**
     * @param list<string> $names
     *
     * @return list<string> the distinct names, sorted
     */
    private function sorted(array $names): array
    {
        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    /**
     * @return Closure(): string
     */
    private function failing(): Closure
    {
        return static function (): string {
            throw new RuntimeException('failed');
        };
    }

    private function plain(): PasswordHasherInterface
    {
        return new class implements PasswordHasherInterface {
            public function hash(string $plainPassword): string
            {
                return 'plain:'.$plainPassword;
            }

            public function verify(string $hashedPassword, string $plainPassword): bool
            {
                return hash_equals($hashedPassword, 'plain:'.$plainPassword);
            }

            public function needsRehash(string $hashedPassword): bool
            {
                return false;
            }
        };
    }
}

/**
 * A lock factory that remembers the locks it made, so that none is given back by being dropped.
 */
final class SpyLockFactory extends LockFactory
{
    /** @var list<string> */
    private array $names = [];

    /** @var list<SharedLockInterface> */
    private array $locks = [];

    /**
     * @return list<string> the resources of the locks made since the last `forget()`
     */
    public function names(): array
    {
        return $this->names;
    }

    /**
     * @return list<SharedLockInterface> the locks made, which stay alive as long as this factory does
     */
    public function locks(): array
    {
        return $this->locks;
    }

    public function forget(): void
    {
        $this->names = [];
    }

    #[Override]
    public function createLock(string $resource, ?float $ttl = 300.0, bool $autoRelease = true): SharedLockInterface
    {
        $this->names[] = $resource;

        return $this->locks[] = parent::createLock($resource, $ttl, $autoRelease);
    }
}

/**
 * A clock that moves only when it sleeps, and that stops a wait which never ends.
 */
final class StepClock implements ClockInterface
{
    /** @var list<float> */
    public array $sleeps = [];

    private int $reads = 0;

    private readonly MockClock $clock;

    private readonly int $start;

    public function __construct()
    {
        $this->clock = new MockClock('2026-11-25 12:00:00.250000');
        $this->start = (int) $this->clock->now()->format('Uu');
    }

    #[Override]
    public function now(): DatePoint
    {
        if (++$this->reads > 10_000) {
            throw new LogicException('The wait never ends.');
        }

        return $this->clock->now();
    }

    #[Override]
    public function sleep(float|int $seconds): void
    {
        $this->sleeps[] = $seconds;
        $this->clock->sleep($seconds);
    }

    #[Override]
    public function withTimeZone(DateTimeZone|string $timezone): static
    {
        return $this;
    }

    /** The microseconds slept so far. */
    public function elapsed(): int
    {
        return (int) $this->clock->now()->format('Uu') - $this->start;
    }
}
