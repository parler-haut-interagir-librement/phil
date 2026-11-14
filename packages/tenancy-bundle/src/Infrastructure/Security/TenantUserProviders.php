<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Security;

use Override;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * A user provider chosen for each tenant: the accounts of one tenant are loaded from its own provider — its own table,
 * its own directory, its own identity provider —, those of a tenant without one from the default provider, if any.
 *
 * The tenant is the one of the request when the firewall runs, which the request's host gives; a request whose tenant
 * is resolved later, from the account's choice, reaches the default provider. With no provider for the tenant and no
 * default, every account is unknown, as is an account whose class the tenant's provider does not handle: the firewall
 * then drops a session's token rather than hand it to another tenant's accounts.
 *
 * The providers are services the container builds only when a tenant asks for one.
 *
 * @template-implements UserProviderInterface<UserInterface>
 */
final readonly class TenantUserProviders implements UserProviderInterface, PasswordUpgraderInterface
{
    /**
     * @param ServiceLocator<UserProviderInterface<UserInterface>> $providers the provider of each tenant, by tenant reference
     * @param ?UserProviderInterface<UserInterface>                $default   the provider of the tenants without one, and of a request in no tenant
     */
    public function __construct(
        private TenantContext $context,
        private ServiceLocator $providers,
        private ?UserProviderInterface $default = null,
    ) {
    }

    #[Override]
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->current($identifier)->loadUserByIdentifier($identifier);
    }

    #[Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        $provider = $this->current($user->getUserIdentifier());

        if (!$provider->supportsClass($user::class)) {
            throw $this->unknown($user->getUserIdentifier());
        }

        return $provider->refreshUser($user);
    }

    /**
     * Whether any of the providers handles the class: the firewall asks it before refreshing, whatever the tenant.
     */
    #[Override]
    public function supportsClass(string $class): bool
    {
        foreach ($this->providers->getProvidedServices() as $tenant => $type) {
            if ($this->providers->get($tenant)->supportsClass($class)) {
                return true;
            }
        }

        return $this->default?->supportsClass($class) ?? false;
    }

    #[Override]
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        $tenant = $this->context->current();
        $provider = $tenant instanceof TenantId && $this->providers->has($tenant->value) ? $this->providers->get($tenant->value) : $this->default;

        if ($provider instanceof PasswordUpgraderInterface) {
            $provider->upgradePassword($user, $newHashedPassword);
        }
    }

    /**
     * @return UserProviderInterface<UserInterface>
     */
    private function current(string $identifier): UserProviderInterface
    {
        $tenant = $this->context->current();

        if ($tenant instanceof TenantId && $this->providers->has($tenant->value)) {
            return $this->providers->get($tenant->value);
        }

        return $this->default ?? throw $this->unknown($identifier);
    }

    private function unknown(string $identifier): UserNotFoundException
    {
        $exception = new UserNotFoundException('No account of this tenant has this identifier.');
        $exception->setUserIdentifier($identifier);

        return $exception;
    }
}
