<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Security;

use Phil\TenancyBundle\Application\Contract\AccountSharing;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Security\ConfiguredAccountSharing;
use Phil\TenancyBundle\Infrastructure\Security\RememberSignInTenant;
use Phil\TenancyBundle\Infrastructure\Security\TenantScopedUserProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

#[CoversClass(TenantScopedUserProvider::class)]
final class TenantScopedUserProviderTest extends TestCase
{
    public function testAnAccountIsRefreshedOnTheTenantItSignedInOn(): void
    {
        $refreshed = $this->provider('rose', 'rose')->refreshUser(new InMemoryUser('ana', null));

        self::assertSame('ana', $refreshed->getUserIdentifier());
    }

    public function testAnAccountIsRefusedOnAnotherTenant(): void
    {
        try {
            $this->provider('rose', 'tavern')->refreshUser(new InMemoryUser('ana', null));
            self::fail('The account was refreshed on another tenant.');
        } catch (UserNotFoundException $userNotFoundException) {
            self::assertSame('ana', $userNotFoundException->getUserIdentifier());
            self::assertSame('The account signed in on another tenant.', $userNotFoundException->getMessage());
        }
    }

    public function testAnAccountIsRefusedOnARequestInNoTenant(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->provider('rose', null)->refreshUser(new InMemoryUser('ana', null));
    }

    public function testASharedAccountIsRefreshedOnAnotherTenant(): void
    {
        $refreshed = $this->provider('rose', 'tavern', new ConfiguredAccountSharing([['rose', 'tavern']]))->refreshUser(new InMemoryUser('ana', null));

        self::assertSame('ana', $refreshed->getUserIdentifier());
    }

    public function testTheSharingIsAskedWithTheAccountAndBothTenants(): void
    {
        $sharing = new class implements AccountSharing {
            /** @var list<string> */
            public array $asked = [];

            public function isShared(string $account, ?TenantId $signedInOn, ?TenantId $visited): bool
            {
                $this->asked[] = $account.':'.($signedInOn->value ?? '-').'>'.($visited->value ?? '-');

                return true;
            }
        };

        $this->provider('rose', 'tavern', $sharing)->refreshUser(new InMemoryUser('ana', null));
        $this->provider('rose', 'rose', $sharing)->refreshUser(new InMemoryUser('ana', null));
        $this->provider(null, 'tavern', $sharing)->refreshUser(new InMemoryUser('ana', null));
        $this->provider(null, null, $sharing)->refreshUser(new InMemoryUser('ana', null));

        self::assertSame(['ana:rose>tavern', 'ana:->tavern'], $sharing->asked);
    }

    /**
     * Without reachable tenants to decide, a session opened on a central host, in no tenant, is signed in there only.
     */
    public function testASessionThatSignedInOnNoTenantIsRefusedOnTheHostOfATenant(): void
    {
        self::assertSame('ana', $this->provider(null, null)->refreshUser(new InMemoryUser('ana', null))->getUserIdentifier());

        try {
            $this->provider(null, 'tavern')->refreshUser(new InMemoryUser('ana', null));
            self::fail('A session opened in no tenant was signed in on a tenant.');
        } catch (UserNotFoundException $userNotFoundException) {
            self::assertSame(['The account signed in on another tenant.', 'ana'], [$userNotFoundException->getMessage(), $userNotFoundException->getUserIdentifier()]);
        }
    }

    public function testASessionThatSignedInOnNoTenantIsSignedInEverywhereWithSharedSessions(): void
    {
        $refreshed = $this->provider(null, 'tavern', new ConfiguredAccountSharing(sharedSessions: true))->refreshUser(new InMemoryUser('ana', null));

        self::assertSame('ana', $refreshed->getUserIdentifier());
    }

    public function testASessionThatSignedInOnNoTenantIsLeftToTheReachableTenantsWhenTheyDecide(): void
    {
        $refreshed = $this->provider(null, 'tavern', reachableTenantsDecide: true)->refreshUser(new InMemoryUser('ana', null));

        self::assertSame('ana', $refreshed->getUserIdentifier());

        $this->expectException(UserNotFoundException::class);
        $this->provider('rose', 'tavern', reachableTenantsDecide: true)->refreshUser(new InMemoryUser('ana', null));
    }

    public function testARequestWithoutASessionIsNotScoped(): void
    {
        $requests = new RequestStack([Request::create('/')]);
        $provider = new TenantScopedUserProvider($this->users(), $this->context('tavern'), $requests, new ConfiguredAccountSharing());

        self::assertSame('ana', $provider->refreshUser(new InMemoryUser('ana', null))->getUserIdentifier());
        self::assertSame('ana', new TenantScopedUserProvider($this->users(), $this->context('tavern'), new RequestStack(), new ConfiguredAccountSharing())->refreshUser(new InMemoryUser('ana', null))->getUserIdentifier());
    }

    public function testARememberedTenantThatIsNoLongerAReferenceRefusesTheAccount(): void
    {
        foreach (['', ' rose', 42] as $remembered) {
            try {
                $this->provider($remembered, 'rose')->refreshUser(new InMemoryUser('ana', null));
                self::fail('The account was refreshed.');
            } catch (UserNotFoundException $refused) {
                self::assertSame('ana', $refused->getUserIdentifier());
            }
        }
    }

    public function testTheApplicationsProviderStillRefreshesTheAccount(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->provider('rose', 'rose')->refreshUser(new InMemoryUser('nobody', null));
    }

    public function testLoadingAndSupportingAreTheApplicationsProviders(): void
    {
        $provider = $this->provider('rose', 'tavern');

        self::assertSame('ana', $provider->loadUserByIdentifier('ana')->getUserIdentifier());
        self::assertTrue($provider->supportsClass(InMemoryUser::class));
        self::assertFalse($provider->supportsClass(self::class));
    }

    public function testAPasswordIsUpgradedByAProviderThatUpgradesThem(): void
    {
        $inner = new class implements UserProviderInterface, PasswordUpgraderInterface {
            public ?string $upgraded = null;

            public function refreshUser(UserInterface $user): UserInterface
            {
                return $user;
            }

            public function supportsClass(string $class): bool
            {
                return true;
            }

            public function loadUserByIdentifier(string $identifier): UserInterface
            {
                return new InMemoryUser($identifier, null);
            }

            public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
            {
                $this->upgraded = $newHashedPassword;
            }
        };

        new TenantScopedUserProvider($inner, new TenantContext(), new RequestStack(), new ConfiguredAccountSharing())->upgradePassword(new InMemoryUser('ana', 'old'), 'new');
        new TenantScopedUserProvider($this->users(), new TenantContext(), new RequestStack(), new ConfiguredAccountSharing())->upgradePassword(new InMemoryUser('ana', 'old'), 'new');

        self::assertSame('new', $inner->upgraded);
    }

    private function provider(mixed $signedInOn, ?string $visited, AccountSharing $sharing = new ConfiguredAccountSharing(), ?bool $reachableTenantsDecide = null): TenantScopedUserProvider
    {
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        if (null !== $signedInOn) {
            $request->getSession()->set(RememberSignInTenant::SESSION_KEY, $signedInOn);
        }

        $requests = new RequestStack([$request]);

        return null === $reachableTenantsDecide
            ? new TenantScopedUserProvider($this->users(), $this->context($visited), $requests, $sharing)
            : new TenantScopedUserProvider($this->users(), $this->context($visited), $requests, $sharing, $reachableTenantsDecide);
    }

    private function context(?string $tenant): TenantContext
    {
        $context = new TenantContext();

        if (null !== $tenant) {
            $context->enter(TenantId::fromString($tenant));
        }

        return $context;
    }

    private function users(): InMemoryUserProvider
    {
        return new InMemoryUserProvider(['ana' => ['roles' => ['ROLE_USER']]]);
    }
}
