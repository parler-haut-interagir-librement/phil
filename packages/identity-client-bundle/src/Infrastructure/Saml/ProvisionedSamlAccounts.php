<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Saml;

use Override;
use Phil\IdentityClientBundle\Application\Contract\UserProvisioner;
use Phil\IdentityClientBundle\Application\Identity\VerifiedIdentity;
use Phil\SamlSp\Attribute\SamlIdentity;
use Phil\SamlSp\Attribute\UserField;
use Phil\SamlSpBundle\Application\Contract\SamlAccounts;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The accounts of `phil/saml-sp-bundle` kept by the {@see UserProvisioner} of the application, so that a person signed
 * in through SAML and a person signed in through OpenID Connect land in the same accounts.
 *
 * The subject handed to the provisioner is the key of the identity provider and the stable identifier of the person
 * there, `acme|5f0c1a52-…`: unique across identity providers, and the identifier `find()` is asked with on later
 * requests. The issuer is the entity ID of the identity provider, as the signed assertion states it. The attributes
 * become claims of the OpenID Connect names, filtered by the same list of kept claims as a sign-in through the provider:
 *
 * | Claim                         | From                                                          |
 * |-------------------------------|---------------------------------------------------------------|
 * | `email`                       | the email field; kept only from an identity provider of `verified_email_from` |
 * | `given_name`, `family_name`   | the name fields                                               |
 * | `name`                        | the display name field                                        |
 * | `groups`, `roles`             | the group and role fields, as lists                           |
 * | `acr`                         | the authentication context of the assertion                   |
 * | `auth_time`                   | the instant of the authentication, in seconds                 |
 * | `sid`                         | the session index at the identity provider                    |
 */
final readonly class ProvisionedSamlAccounts implements SamlAccounts
{
    private const string SEPARATOR = '|';

    /**
     * @param list<string> $keptClaims        the names of the claims handed to the provisioner
     * @param list<string> $verifiedEmailFrom the keys of the identity providers trusted to verify the addresses they send
     */
    public function __construct(
        private UserProvisioner $provisioner,
        private array $keptClaims,
        private array $verifiedEmailFrom = [],
    ) {
    }

    /**
     * The subject of this person in the provisioner: the key of the identity provider, `|`, the stable identifier.
     */
    public static function subject(string $identityProvider, SamlIdentity $identity): string
    {
        return $identityProvider.self::SEPARATOR.$identity->subject();
    }

    /**
     * The account of a person who signed in before, brought up to date with this sign-in; null for a first sign-in.
     */
    #[Override]
    public function find(string $identityProvider, SamlIdentity $identity): ?UserInterface
    {
        if (!$this->provisioner->find(self::subject($identityProvider, $identity)) instanceof UserInterface) {
            return null;
        }

        return $this->provisioner->provision($this->identity($identityProvider, $identity));
    }

    #[Override]
    public function create(string $identityProvider, SamlIdentity $identity): UserInterface
    {
        return $this->provisioner->provision($this->identity($identityProvider, $identity));
    }

    /**
     * The identity the provisioner receives for this sign-in.
     */
    public function identity(string $identityProvider, SamlIdentity $identity): VerifiedIdentity
    {
        return VerifiedIdentity::fromClaims($identity->issuer, self::subject($identityProvider, $identity), $this->claims($identityProvider, $identity), $this->keptClaims);
    }

    /**
     * @return array<string, bool|int|string|list<string>>
     */
    private function claims(string $identityProvider, SamlIdentity $identity): array
    {
        $claims = array_filter([
            'email' => $identity->value(UserField::Email),
            'given_name' => $identity->value(UserField::GivenName),
            'family_name' => $identity->value(UserField::FamilyName),
            'name' => $identity->value(UserField::DisplayName),
            'acr' => $identity->authnContext,
            'sid' => $identity->sessionIndex,
        ], static fn (?string $value): bool => null !== $value && '' !== $value);

        foreach (['groups' => UserField::Groups, 'roles' => UserField::Roles] as $name => $field) {
            $values = $identity->values($field);
            if ([] !== $values) {
                $claims[$name] = $values;
            }
        }

        $claims['email_verified'] = \in_array($identityProvider, $this->verifiedEmailFrom, true);
        $claims['auth_time'] = $identity->authnInstant->getTimestamp();

        return $claims;
    }
}
