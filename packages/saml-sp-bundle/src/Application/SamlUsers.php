<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Application;

use Phil\SamlSp\Attribute\SamlIdentity;
use Phil\SamlSp\Attribute\UserField;
use Phil\SamlSpBundle\Application\Contract\SamlAccounts;
use Phil\SamlSpBundle\Application\Event\SamlAccountCreated;
use Phil\SamlSpBundle\Application\Event\SamlLoginSucceeded;
use Phil\SamlSpBundle\Application\Exception\AccountRefused;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The user of the application for the identity an assertion gave: through `SamlAccounts` when the application has
 * accounts — found, or created at the first sign-in when the identity provider may create them —, else a `SamlUser`
 * with the roles of the configuration. `SamlAccountCreated` and `SamlLoginSucceeded` follow.
 */
final readonly class SamlUsers
{
    public function __construct(
        private IdentityProviders $providers,
        private EventDispatcherInterface $events,
        private ?SamlAccounts $accounts = null,
    ) {
    }

    public function user(string $identityProvider, SamlIdentity $identity): UserInterface
    {
        $settings = $this->providers->settings($identityProvider);
        $user = $this->accounts instanceof SamlAccounts ? $this->account($this->accounts, $settings, $identity) : SamlUser::fromIdentity($identityProvider, $identity, self::roles($settings, $identity));

        $this->events->dispatch(new SamlLoginSucceeded($identityProvider, $identity, $user));

        return $user;
    }

    private function account(SamlAccounts $accounts, IdentityProviderSettings $settings, SamlIdentity $identity): UserInterface
    {
        $user = $accounts->find($settings->key, $identity);

        if ($user instanceof UserInterface) {
            return $user;
        }

        if (!$settings->createAccounts) {
            throw AccountRefused::noAccount($settings->key);
        }

        $user = $accounts->create($settings->key, $identity);
        $this->events->dispatch(new SamlAccountCreated($settings->key, $identity, $user));

        return $user;
    }

    /**
     * The roles of the configuration for this identity: the default roles of the identity provider, then the role each
     * group or role it sends is mapped to, without repeats.
     *
     * @return list<string>
     */
    public static function roles(IdentityProviderSettings $settings, SamlIdentity $identity): array
    {
        $roles = $settings->defaultRoles;

        foreach ([...$identity->values(UserField::Groups), ...$identity->values(UserField::Roles)] as $group) {
            if (isset($settings->groupRoles[$group])) {
                $roles[] = $settings->groupRoles[$group];
            }
        }

        return array_values(array_unique($roles));
    }
}
