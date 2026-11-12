<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Security;

use Override;
use Phil\IdentityClientBundle\Application\Contract\UserProvisioner;
use Phil\IdentityClientBundle\Application\Identity\VerifiedIdentity;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\AttributesBasedUserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The user provider of the firewall, over the {@see UserProvisioner} of the application.
 *
 * @implements AttributesBasedUserProviderInterface<UserInterface>
 */
final readonly class IdentityUserProvider implements AttributesBasedUserProviderInterface
{
    /**
     * @param list<string> $keptClaims
     */
    public function __construct(
        private UserProvisioner $provisioner,
        private string $issuer,
        private array $keptClaims,
    ) {
    }

    /**
     * @param array<array-key, mixed> $attributes the claims of a sign-in; empty when the user is only looked up
     */
    #[Override]
    public function loadUserByIdentifier(string $identifier, array $attributes = []): UserInterface
    {
        if ([] === $attributes) {
            return $this->find($identifier);
        }

        return $this->provisioner->provision(VerifiedIdentity::fromClaims($this->issuer, $identifier, $attributes, $this->keptClaims));
    }

    #[Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->find($user->getUserIdentifier());
    }

    #[Override]
    public function supportsClass(string $class): bool
    {
        return true;
    }

    private function find(string $subject): UserInterface
    {
        $user = $this->provisioner->find($subject);
        if (!$user instanceof UserInterface) {
            $exception = new UserNotFoundException();
            $exception->setUserIdentifier($subject);

            throw $exception;
        }

        return $user;
    }
}
