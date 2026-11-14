<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Security;

use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Security\TenantUserProviders;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

#[CoversClass(TenantUserProviders::class)]
final class TenantUserProvidersTest extends TestCase
{
    public function testEachTenantLoadsItsAccountsFromItsOwnProvider(): void
    {
        $context = new TenantContext();
        $providers = $this->providers($context, withDefault: true);

        $context->enter(TenantId::fromString('rose'));
        self::assertSame('ana', $providers->loadUserByIdentifier('ana')->getUserIdentifier());
        $this->assertUnknown('ben', static fn (): UserInterface => $providers->loadUserByIdentifier('ben'));

        $context->enter(TenantId::fromString('tavern'));
        self::assertSame('ben', $providers->loadUserByIdentifier('ben')->getUserIdentifier());
        $this->assertUnknown('ana', static fn (): UserInterface => $providers->loadUserByIdentifier('ana'));
    }

    public function testTheOtherTenantsAndNoTenantLoadFromTheDefault(): void
    {
        $context = new TenantContext();
        $providers = $this->providers($context, withDefault: true);

        self::assertSame('sam', $providers->loadUserByIdentifier('sam')->getUserIdentifier());

        $context->enter(TenantId::fromString('general'));
        self::assertSame('sam', $providers->loadUserByIdentifier('sam')->getUserIdentifier());
        $this->assertUnknown('ana', static fn (): UserInterface => $providers->loadUserByIdentifier('ana'));
    }

    public function testWithoutADefaultTheOtherTenantsHaveNoAccount(): void
    {
        $context = new TenantContext();
        $providers = $this->providers($context);

        try {
            $providers->loadUserByIdentifier('sam');
            self::fail('An account was found in no tenant.');
        } catch (UserNotFoundException $userNotFoundException) {
            self::assertSame('sam', $userNotFoundException->getUserIdentifier());
            self::assertSame('No account of this tenant has this identifier.', $userNotFoundException->getMessage());
        }

        $context->enter(TenantId::fromString('general'));
        $this->assertUnknown('sam', static fn (): UserInterface => $providers->refreshUser(new InMemoryUser('sam', null)));
    }

    public function testAnAccountIsRefreshedByTheProviderOfTheTenant(): void
    {
        $context = new TenantContext();
        $providers = $this->providers($context, withDefault: true);

        $context->enter(TenantId::fromString('rose'));
        self::assertSame(['ROLE_ROSE'], $providers->refreshUser(new InMemoryUser('ana', null))->getRoles());
        $this->assertUnknown('ben', static fn (): UserInterface => $providers->refreshUser(new InMemoryUser('ben', null)));
    }

    public function testAnAccountOfAClassTheTenantsProviderDoesNotHandleIsUnknown(): void
    {
        $context = new TenantContext();
        $context->enter(TenantId::fromString('rose'));

        $providers = new TenantUserProviders($context, new ServiceLocator(['rose' => static fn (): UserProviderInterface => new InMemoryUserProvider(['ana' => []])]));
        $other = new class implements UserInterface {
            public function getRoles(): array
            {
                return [];
            }

            public function getUserIdentifier(): string
            {
                return 'ana';
            }
        };

        try {
            $providers->refreshUser($other);
            self::fail('An account of another class was refreshed.');
        } catch (UserNotFoundException $userNotFoundException) {
            self::assertSame('ana', $userNotFoundException->getUserIdentifier());
        }
    }

    public function testTheClassesOfEveryProviderAreSupported(): void
    {
        $context = new TenantContext();
        $tenantOnly = new TenantUserProviders($context, new ServiceLocator(['rose' => static fn (): UserProviderInterface => new InMemoryUserProvider()]));
        $defaultOnly = new TenantUserProviders($context, new ServiceLocator([]), new InMemoryUserProvider());

        self::assertTrue($tenantOnly->supportsClass(InMemoryUser::class));
        self::assertTrue($defaultOnly->supportsClass(InMemoryUser::class));
        self::assertFalse($tenantOnly->supportsClass(self::class));
        self::assertFalse($defaultOnly->supportsClass(self::class));
        self::assertFalse(new TenantUserProviders($context, new ServiceLocator([]))->supportsClass(InMemoryUser::class));
    }

    public function testAPasswordIsUpgradedByTheProviderOfTheTenant(): void
    {
        $context = new TenantContext();
        $rose = $this->upgrader();
        $default = $this->upgrader();
        $providers = new TenantUserProviders($context, new ServiceLocator(['rose' => static fn (): UserProviderInterface => $rose, 'tavern' => static fn (): UserProviderInterface => new InMemoryUserProvider()]), $default);
        $user = new InMemoryUser('ana', 'old');

        $context->enter(TenantId::fromString('rose'));
        $providers->upgradePassword($user, 'new-rose');

        $context->enter(TenantId::fromString('tavern'));
        $providers->upgradePassword($user, 'new-tavern');

        $context->enter(TenantId::fromString('general'));
        $providers->upgradePassword($user, 'new-general');

        $context->leave();
        $providers->upgradePassword($user, 'new-none');
        new TenantUserProviders($context, new ServiceLocator([]))->upgradePassword($user, 'nowhere');

        self::assertSame(['new-rose'], $rose->upgraded);
        self::assertSame(['new-general', 'new-none'], $default->upgraded);
    }

    private function providers(TenantContext $context, bool $withDefault = false): TenantUserProviders
    {
        return new TenantUserProviders(
            $context,
            new ServiceLocator([
                'rose' => static fn (): UserProviderInterface => new InMemoryUserProvider(['ana' => ['roles' => ['ROLE_ROSE']]]),
                'tavern' => static fn (): UserProviderInterface => new InMemoryUserProvider(['ben' => []]),
            ]),
            $withDefault ? new InMemoryUserProvider(['sam' => []]) : null,
        );
    }

    /**
     * @return UserProviderInterface<UserInterface>&PasswordUpgraderInterface&object{upgraded: list<string>}
     */
    private function upgrader(): UserProviderInterface&PasswordUpgraderInterface
    {
        return new class implements UserProviderInterface, PasswordUpgraderInterface {
            /** @var list<string> */
            public array $upgraded = [];

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
                $this->upgraded[] = $newHashedPassword;
            }
        };
    }

    private function assertUnknown(string $identifier, callable $load): void
    {
        try {
            $load();
            self::fail('The account was found.');
        } catch (UserNotFoundException $userNotFoundException) {
            self::assertSame($identifier, $userNotFoundException->getUserIdentifier());
        }
    }
}
