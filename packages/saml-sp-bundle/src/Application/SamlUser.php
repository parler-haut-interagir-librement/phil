<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Application;

use Override;
use Phil\SamlSp\Attribute\SamlIdentity;
use Phil\SamlSp\Attribute\UserField;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The user an identity provider signs in when the application has no `SamlAccounts`: identified by the key of the
 * identity provider and the stable identifier of the person there, with the roles of the configuration, the email
 * address and the display name. It lives in the session only.
 */
final readonly class SamlUser implements UserInterface
{
    /**
     * @param non-empty-string $identifier
     * @param list<string>     $roles
     */
    public function __construct(
        private string $identifier,
        private array $roles,
        public string $identityProvider,
        public ?string $email = null,
        public ?string $displayName = null,
    ) {
    }

    /**
     * @param list<string> $roles
     */
    public static function fromIdentity(string $identityProvider, SamlIdentity $identity, array $roles): self
    {
        $displayName = $identity->value(UserField::DisplayName) ?? trim(($identity->value(UserField::GivenName) ?? '').' '.($identity->value(UserField::FamilyName) ?? ''));

        return new self($identityProvider.'|'.$identity->subject(), $roles, $identityProvider, $identity->value(UserField::Email), '' === $displayName ? null : $displayName);
    }

    #[Override]
    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function getRoles(): array
    {
        return $this->roles;
    }
}
