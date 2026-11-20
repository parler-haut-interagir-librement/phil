<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Account\AccountAddress;
use Phil\IdentityProviderBundle\Domain\Model\External\LinkedIdentity;
use Phil\IdentityProviderBundle\Domain\Model\Link\LinkToken;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\AuthorizationCode;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Consent;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\RevokedFamily;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\SigningKey;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\UsedAssertion;
use Phil\IdentityProviderBundle\Domain\Model\Passkey\Passkey;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Phil\IdentityProviderBundle\Domain\Model\Totp\RecoveryCodeSet;
use Phil\IdentityProviderBundle\Domain\Model\Totp\TotpCredential;

/**
 * Gives the keys of the bundle's tables the binary collation of MySQL and MariaDB as the ORM loads their metadata, so
 * that the schema it generates, and the migration `doctrine:migrations:diff` writes, compare them byte by byte: the
 * client `App` is never `app`, the subject `AbC` an external provider gave is never linked as `abc`, an address with
 * an accent is never the one without, and a session, a token or a code is found by its exact identifier only.
 * PostgreSQL's default collations are deterministic and SQLite compares bytes: their columns keep the default, and the
 * mapping stays the same for every platform.
 */
final readonly class BinaryKeysListener
{
    /**
     * The fields that compare byte by byte, by entity of the bundle: identifiers, and the columns rows are found by.
     */
    public const array FIELDS = [
        Account::class => ['id', 'email', 'emailCanonical'],
        AccountAddress::class => ['id', 'accountId', 'canonical'],
        LinkToken::class => ['id', 'accountId'],
        AccountSession::class => ['id', 'accountId'],
        Passkey::class => ['id', 'accountId'],
        TotpCredential::class => ['accountId'],
        RecoveryCodeSet::class => ['accountId'],
        LinkedIdentity::class => ['id', 'accountId', 'provider', 'subject'],
        Client::class => ['id'],
        Consent::class => ['id', 'accountId', 'clientId'],
        AuthorizationCode::class => ['id', 'clientId', 'accountId', 'sessionId', 'family'],
        IssuedToken::class => ['id', 'family', 'clientId', 'accountId', 'sessionId'],
        SigningKey::class => ['kid'],
        UsedAssertion::class => ['id'],
        RevokedFamily::class => ['family'],
    ];

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();
        $fields = self::FIELDS[$metadata->getName()] ?? [];

        if ([] === $fields || !$event->getObjectManager()->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return;
        }

        foreach ($fields as $field) {
            $mapping = $metadata->getFieldMapping($field);
            $mapping->options = [...$mapping->options ?? [], 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'];
        }
    }
}
