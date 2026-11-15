<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application;

use Phil\IdentityProviderBundle\Application\Accounts;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Event\IdentityEvent;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\Journal;
use Phil\IdentityProviderBundle\Application\PasswordRules;
use Phil\IdentityProviderBundle\Domain\Security\LockoutPolicy;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(Accounts::class)]
#[CoversClass(PasswordRules::class)]
#[CoversClass(Journal::class)]
#[CoversClass(IdentityEvent::class)]
#[CoversClass(Refused::class)]
#[CoversClass(AccountUser::class)]
final class AccountsTest extends TestCase
{
    public function testRegistration(): void
    {
        $services = new Services();
        $events = [];
        $services->events->addListener(IdentityEvent::class, static function (IdentityEvent $event) use (&$events): void {
            $events[] = $event;
        });
        $account = $services->accounts()->register(' Ada@Example.com ', Services::STRONG, 'Ada');

        self::assertNotNull($account);
        self::assertSame(26, \strlen($account->id()));
        self::assertSame('ada@example.com', $account->email());
        self::assertSame('Ada', $account->displayName());
        self::assertSame(Services::STRONG, $account->passwordHash());
        self::assertSame($services->clock->now()->getTimestamp(), $account->passwordChangedAt()?->getTimestamp());
        self::assertEquals($account, $services->accounts->get($account->id()));
        self::assertSame([IdentityEventType::AccountRegistered->value], $services->audit->actions());
        self::assertCount(1, $events);
        self::assertSame(IdentityEventType::AccountRegistered, $events[0]->type);
        self::assertSame($account->id(), $events[0]->accountId);
        self::assertSame([], $events[0]->context);
    }

    public function testAKnownAddressCreatesNothingAndSaysNothingDifferent(): void
    {
        $services = new Services();
        $account = $services->account();

        self::assertNull($services->accounts()->register('ADA@example.com', Services::STRONG));
        self::assertSame([IdentityEventType::AccountRegistered->value, IdentityEventType::RegistrationForKnownEmail->value], $services->audit->actions());
        self::assertSame($account->id(), $services->audit->records[1][1]);
    }

    public function testAnAccountWithoutPassword(): void
    {
        $services = new Services();
        $account = $services->accounts()->register('ada@example.com', null);

        self::assertNotNull($account);
        self::assertFalse($account->hasPassword());
        self::assertNull($account->passwordChangedAt());
    }

    public function testRegistrationRefusals(): void
    {
        $services = new Services();

        foreach ([
            ['not an address', Services::STRONG, null, 'email.invalid'],
            ['ada@example.com', 'short', null, 'password.too_short'],
            ['ada@example.com', 'password', null, 'password.too_weak'],
            ['ada@example.com', Services::STRONG, str_repeat('n', 101), 'name.too_long'],
        ] as [$email, $password, $name, $reason]) {
            try {
                $services->accounts()->register($email, $password, $name);
                self::fail($reason);
            } catch (Refused $refused) {
                self::assertSame([$reason], $refused->reasons);
                self::assertSame($reason, $refused->getMessage());
            }
        }

        self::assertSame([], $services->audit->actions());
    }

    public function testThePasswordRules(): void
    {
        $services = new Services(breaches: true);
        $account = $services->account();

        self::assertSame([], $services->rules->problems(Services::STRONG));
        self::assertSame(['password.too_short'], $services->rules->problems('1234567'));
        self::assertSame(['password.too_long'], $services->rules->problems(str_repeat('a', 4097)));
        self::assertSame(['password.too_weak'], $services->rules->problems('ada@example.com', $account));
        self::assertSame(['password.too_weak'], $services->rules->problems('qwertyuiop'));
        self::assertSame(['password.too_weak'], $services->rules->problems('xenoblaster1984', email: 'xenoblaster1984@example.com'));
        self::assertSame(['password.breached'], $services->rules->problems('zebra crossing violin quartz'));
        self::assertSame([], new Services()->rules->problems('zebra crossing violin quartz'));
        self::assertSame([], $services->rules->problems(str_repeat('é', 8).' violin quartz'));
    }

    public function testTheAddressAndItsVerification(): void
    {
        $services = new Services();
        $accounts = $services->accounts();
        $account = $services->account();
        $services->account('bob@example.com');

        $accounts->verifyEmail($account);
        $accounts->verifyEmail($account);
        self::assertTrue($services->accounts->get($account->id())?->isEmailVerified());
        self::assertCount(1, array_keys($services->audit->actions(), IdentityEventType::EmailVerified->value, true));

        $accounts->changeEmail($account, 'ADA@example.com');
        self::assertTrue($account->isEmailVerified());
        $accounts->changeEmail($account, 'ada2@example.com');
        self::assertFalse($account->isEmailVerified());
        self::assertEquals($account, $accounts->findByEmail('ada2@example.com'));
        self::assertEquals($account, $accounts->get($account->id()));
        self::assertContains(IdentityEventType::EmailChanged->value, $services->audit->actions());

        foreach (['bob@example.com' => 'email.taken', 'nope' => 'email.invalid'] as $email => $reason) {
            try {
                $accounts->changeEmail($account, $email);
                self::fail($reason);
            } catch (Refused $refused) {
                self::assertSame([$reason], $refused->reasons);
            }
        }

        $accounts->rename($account, 'Ada L.');
        self::assertSame('Ada L.', $account->displayName());

        $this->expectException(Refused::class);
        $accounts->rename($account, str_repeat('n', 101));
    }

    public function testThePassword(): void
    {
        $services = new Services();
        $accounts = $services->accounts();
        $account = $services->account();

        try {
            $accounts->changePassword($account, 'violet staple horse battery correct', 'wrong');
            self::fail('Changed with a wrong password.');
        } catch (Refused $refused) {
            self::assertSame(['password.current_invalid'], $refused->reasons);
        }

        try {
            $accounts->changePassword($account, 'short', Services::STRONG);
            self::fail('Changed to a short password.');
        } catch (Refused $refused) {
            self::assertSame(['password.too_short'], $refused->reasons);
        }

        $services->clock->modify('+1 hour');
        $accounts->changePassword($account, 'violet staple horse battery correct', Services::STRONG);
        self::assertSame('violet staple horse battery correct', $account->passwordHash());
        self::assertSame($services->clock->now()->getTimestamp(), $account->passwordChangedAt()?->getTimestamp());
        $accounts->changePassword($account, Services::STRONG);
        self::assertSame(Services::STRONG, $account->passwordHash());

        $accounts->removePassword($account);
        self::assertFalse($account->hasPassword());
        self::assertSame([IdentityEventType::PasswordChanged->value, $account->id(), ['removed' => true]], array_last($services->audit->records));
        $accounts->changePassword($account, Services::STRONG, 'anything');
        self::assertTrue($account->hasPassword());
    }

    public function testFailuresLockTheAccountOnce(): void
    {
        $services = new Services();
        $accounts = $services->accounts(new LockoutPolicy(2, 60, 60));
        $account = $services->account();

        $accounts->recordFailedSignIn($account);
        self::assertFalse($account->isLocked($services->clock->now()));
        $accounts->recordFailedSignIn($account);
        self::assertTrue($account->isLocked($services->clock->now()));
        $accounts->recordFailedSignIn($account);

        $locks = array_values(array_filter($services->audit->records, static fn (array $record): bool => IdentityEventType::AccountLocked->value === $record[0]));
        self::assertCount(1, $locks);
        self::assertSame(['until' => $services->clock->now()->getTimestamp() + 60], $locks[0][2]);
        self::assertSame(['failures' => 3], $services->audit->last()[2]);

        $accounts->recordSignIn($account);
        self::assertSame(0, $account->failedSignIns());
        self::assertFalse($account->isLocked($services->clock->now()));

        $saved = clone $account;
        $accounts->recordSignIn($account);
        self::assertEquals($saved, $account);
        $accounts->save($account);
    }

    public function testTheUserOfSymfonySecurity(): void
    {
        $services = new Services();
        $account = $services->account();
        $user = new AccountUser($account);

        self::assertSame($account->id(), $user->getUserIdentifier());
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertSame(Services::STRONG, $user->getPassword());
        self::assertSame('ada@example.com', $user->getEmail());
        self::assertNull($user->getEmailVerifiedAt());
        self::assertSame($account->passwordChangedAt(), $user->getPasswordChangedAt());
        self::assertTrue($user->isEqualTo(new AccountUser($account)));
        self::assertFalse($user->isEqualTo(new InMemoryUser($account->id(), null)));
        self::assertFalse($user->isEqualTo(new AccountUser($services->account('bob@example.com'))));

        $copy = clone $account;
        $copy->grantRoles(['ROLE_ADMIN']);
        self::assertFalse($user->isEqualTo(new AccountUser($copy)));
        $copy = clone $account;
        $copy->disable($services->clock->now());
        self::assertFalse($user->isEqualTo(new AccountUser($copy)));
        $copy = clone $account;
        $copy->changePassword('other', $services->clock->now());
        self::assertTrue($user->isEqualTo(new AccountUser($copy)));
    }

    public function testAJournalWithoutDispatcher(): void
    {
        $services = new Services();
        new Journal($services->audit)->record(IdentityEventType::SessionOpened, null, ['session' => 's']);

        self::assertSame([[IdentityEventType::SessionOpened->value, null, ['session' => 's']]], $services->audit->records);
    }
}
