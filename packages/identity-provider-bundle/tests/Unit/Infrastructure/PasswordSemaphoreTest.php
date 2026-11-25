<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Infrastructure;

use Closure;
use DateTimeImmutable;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Infrastructure\Security\PasswordCheckSemaphore;
use Phil\IdentityProviderBundle\Infrastructure\Security\SemaphorePasswordHasher;
use Phil\IdentityProviderBundle\Infrastructure\Security\SemaphorePasswordHasherFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
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
        $locks = new LockFactory(new InMemoryStore());
        $semaphore = new PasswordCheckSemaphore($locks, 1, 0.0);

        self::assertSame('first', $semaphore->run(static fn (): string => 'first'));
        self::assertSame('second', $semaphore->run(static fn (): string => 'second'));
        self::assertTrue($locks->createLock('phil_identity_provider.password_check.0')->acquire());
    }

    public function testASlotIsGivenBackWhenTheHashFails(): void
    {
        $locks = new LockFactory(new InMemoryStore());
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

        for ($i = 0; $i < 7; ++$i) {
            $locks->createLock('phil_identity_provider.password_check.'.$i)->acquire();
        }

        $semaphore = new PasswordCheckSemaphore($locks, 8, 0.0);

        for ($round = 0; $round < 20; ++$round) {
            self::assertSame($round, $semaphore->run(static fn (): int => $round));
        }
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
