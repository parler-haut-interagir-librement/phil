<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use Phil\IdentityProviderBundle\Application\Accounts;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Journal;
use Phil\IdentityProviderBundle\Application\PasswordRules;
use Phil\IdentityProviderBundle\Application\Sessions;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Security\LockoutPolicy;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountRepository;
use Phil\Passwords\Breach\BreachChecker;
use Phil\Passwords\Breach\InMemoryBreachSource;
use Phil\Passwords\Strength\StrengthEstimator;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;

/**
 * The application services wired on memory stores, a clock that moves only when told, and a fast password hasher, for
 * the tests.
 */
final class Services
{
    public const string STRONG = 'correct horse battery staple violet';

    public MockClock $clock;

    public RecordingAudit $audit;

    public EventDispatcher $events;

    public Journal $journal;

    public InMemoryAccountRepository $accounts;

    public UserPasswordHasher $hasher;

    public PasswordRules $rules;

    private static ?StrengthEstimator $estimator = null;

    public function __construct(string $now = '2026-11-14 10:00:00', bool $breaches = false)
    {
        $this->clock = new MockClock($now);
        $this->audit = new RecordingAudit();
        $this->events = new EventDispatcher();
        $this->journal = new Journal($this->audit, $this->events);
        $this->accounts = new InMemoryAccountRepository();
        $this->hasher = new UserPasswordHasher(new PasswordHasherFactory([AccountUser::class => ['algorithm' => 'plaintext']]));
        $this->rules = new PasswordRules(self::$estimator ??= new StrengthEstimator(), breaches: $breaches ? new BreachChecker(InMemoryBreachSource::fromPasswords(['zebra crossing violin quartz' => 5])) : null);
    }

    public function accounts(?LockoutPolicy $policy = null, ?Sessions $sessions = null): Accounts
    {
        return new Accounts($this->accounts, $this->hasher, $this->rules, $this->journal, $this->clock, $policy ?? new LockoutPolicy(), $sessions);
    }

    public function account(string $email = 'ada@example.com', ?string $password = self::STRONG): Account
    {
        $account = $this->accounts()->register($email, $password, 'Ada');
        \assert($account instanceof Account);

        return $account;
    }
}
