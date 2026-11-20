<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\External\LinkedIdentity;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\AuthorizationCode;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Consent;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\SigningKey;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\UsedAssertion;
use Phil\IdentityProviderBundle\Domain\Model\Passkey\Passkey;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Phil\IdentityProviderBundle\Domain\Model\Totp\RecoveryCodeSet;
use Phil\IdentityProviderBundle\Domain\Model\Totp\TotpCredential;
use Phil\IdentityProviderBundle\Domain\Oidc\TokenKind;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RegisteredCredential;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The tables the models map, column by column: a length, a nullable column or a default changed by accident would
 * change every deployed schema.
 */
#[CoversClass(Account::class)]
#[CoversClass(AccountSession::class)]
#[CoversClass(AuthorizationCode::class)]
#[CoversClass(Client::class)]
#[CoversClass(Consent::class)]
#[CoversClass(IssuedToken::class)]
#[CoversClass(LinkedIdentity::class)]
#[CoversClass(Passkey::class)]
#[CoversClass(RecoveryCodeSet::class)]
#[CoversClass(SigningKey::class)]
#[CoversClass(TotpCredential::class)]
#[CoversClass(UsedAssertion::class)]
final class MappingTest extends TestCase
{
    /**
     * Per model: its table, its fields as [column, type, length, nullable, options, identifier], its indexes and its
     * unique constraints.
     */
    private const array MAPPING = [
        'Account' => [
            'table' => 'phil_identity_account',
            'fields' => [
                'id' => ['id', 'string', 26, false, ['fixed' => true], true],
                'email' => ['email', 'string', 180, false, [], false],
                'createdAt' => ['created_at', 'datetime_immutable', null, false, [], false],
                'displayName' => ['display_name', 'string', 100, true, [], false],
                'emailVerifiedAt' => ['email_verified_at', 'datetime_immutable', null, true, [], false],
                'passwordHash' => ['password_hash', 'string', 255, true, [], false],
                'passwordChangedAt' => ['password_changed_at', 'datetime_immutable', null, true, [], false],
                'phone' => ['phone', 'string', 16, true, [], false],
                'phoneVerifiedAt' => ['phone_verified_at', 'datetime_immutable', null, true, [], false],
                'roles' => ['roles', 'json', null, false, [], false],
                'failedSignIns' => ['failed_sign_ins', 'integer', null, false, ['default' => 0], false],
                'lockedUntil' => ['locked_until', 'datetime_immutable', null, true, [], false],
                'disabledAt' => ['disabled_at', 'datetime_immutable', null, true, [], false],
            ],
            'indexes' => [],
            'unique' => ['phil_identity_account_email' => ['email']],
        ],
        'AccountSession' => [
            'table' => 'phil_identity_session',
            'fields' => [
                'id' => ['id', 'string', 43, false, ['fixed' => true], true],
                'accountId' => ['account_id', 'string', 26, false, ['fixed' => true], false],
                'device' => ['device', 'string', 100, false, [], false],
                'methods' => ['methods', 'json', null, false, [], false],
                'risky' => ['risky', 'boolean', null, false, [], false],
                'createdAt' => ['created_at', 'datetime_immutable', null, false, [], false],
                'lastSeenAt' => ['last_seen_at', 'datetime_immutable', null, false, [], false],
                'closedAt' => ['closed_at', 'datetime_immutable', null, true, [], false],
            ],
            'indexes' => ['phil_identity_session_account' => ['account_id']],
            'unique' => [],
        ],
        'AuthorizationCode' => [
            'table' => 'phil_identity_code',
            'fields' => [
                'id' => ['id', 'string', 64, false, ['fixed' => true], true],
                'clientId' => ['client_id', 'string', 64, false, [], false],
                'accountId' => ['account_id', 'string', 26, false, ['fixed' => true], false],
                'redirectUri' => ['redirect_uri', 'text', null, false, [], false],
                'scopes' => ['scopes', 'json', null, false, [], false],
                'nonce' => ['nonce', 'string', 255, true, [], false],
                'codeChallenge' => ['code_challenge', 'string', 128, true, [], false],
                'authTime' => ['auth_time', 'datetime_immutable', null, false, [], false],
                'methods' => ['methods', 'json', null, false, [], false],
                'sessionId' => ['session_id', 'string', 43, true, ['fixed' => true], false],
                'family' => ['family', 'string', 26, false, ['fixed' => true], false],
                'expiresAt' => ['expires_at', 'datetime_immutable', null, false, [], false],
                'usedAt' => ['used_at', 'datetime_immutable', null, true, [], false],
            ],
            'indexes' => ['phil_identity_code_expires_at' => ['expires_at']],
            'unique' => [],
        ],
        'Client' => [
            'table' => 'phil_identity_client',
            'fields' => [
                'id' => ['id', 'string', 64, false, [], true],
                'name' => ['name', 'string', 100, false, [], false],
                'secretHash' => ['secret_hash', 'string', 64, true, ['fixed' => true], false],
                'redirectUris' => ['redirect_uris', 'json', null, false, [], false],
                'postLogoutRedirectUris' => ['post_logout_redirect_uris', 'json', null, false, [], false],
                'scopes' => ['scopes', 'json', null, false, [], false],
                'firstParty' => ['first_party', 'boolean', null, false, [], false],
                'createdAt' => ['created_at', 'datetime_immutable', null, false, [], false],
                'disabledAt' => ['disabled_at', 'datetime_immutable', null, true, [], false],
                'requirePkce' => ['require_pkce', 'boolean', null, false, ['default' => true], false],
                'jwks' => ['jwks', 'json', null, true, [], false],
                'resources' => ['resources', 'json', null, true, [], false],
                'backchannelLogoutUri' => ['backchannel_logout_uri', 'text', null, true, [], false],
                'sectorIdentifier' => ['sector_identifier', 'string', 253, true, [], false],
            ],
            'indexes' => [],
            'unique' => [],
        ],
        'Consent' => [
            'table' => 'phil_identity_consent',
            'fields' => [
                'id' => ['id', 'string', 64, false, ['fixed' => true], true],
                'accountId' => ['account_id', 'string', 26, false, ['fixed' => true], false],
                'clientId' => ['client_id', 'string', 64, false, [], false],
                'scopes' => ['scopes', 'json', null, false, [], false],
                'grantedAt' => ['granted_at', 'datetime_immutable', null, false, [], false],
            ],
            'indexes' => ['phil_identity_consent_account' => ['account_id']],
            'unique' => [],
        ],
        'IssuedToken' => [
            'table' => 'phil_identity_token',
            'fields' => [
                'id' => ['id', 'string', 64, false, [], true],
                'kind' => ['kind', 'string', 10, false, [], false],
                'family' => ['family', 'string', 26, false, ['fixed' => true], false],
                'clientId' => ['client_id', 'string', 64, false, [], false],
                'accountId' => ['account_id', 'string', 26, true, ['fixed' => true], false],
                'scopes' => ['scopes', 'json', null, false, [], false],
                'sessionId' => ['session_id', 'string', 43, true, ['fixed' => true], false],
                'authTime' => ['auth_time', 'datetime_immutable', null, false, [], false],
                'methods' => ['methods', 'json', null, false, [], false],
                'issuedAt' => ['issued_at', 'datetime_immutable', null, false, [], false],
                'expiresAt' => ['expires_at', 'datetime_immutable', null, false, [], false],
                'rotatedAt' => ['rotated_at', 'datetime_immutable', null, true, [], false],
                'revokedAt' => ['revoked_at', 'datetime_immutable', null, true, [], false],
                'audience' => ['audience', 'string', 255, true, [], false],
            ],
            'indexes' => ['phil_identity_token_family' => ['family'], 'phil_identity_token_account' => ['account_id'], 'phil_identity_token_expires_at' => ['expires_at']],
            'unique' => [],
        ],
        'LinkedIdentity' => [
            'table' => 'phil_identity_linked',
            'fields' => [
                'id' => ['id', 'string', 64, false, ['fixed' => true], true],
                'accountId' => ['account_id', 'string', 26, false, ['fixed' => true], false],
                'provider' => ['provider', 'string', 64, false, [], false],
                'subject' => ['subject', 'string', 255, false, [], false],
                'linkedAt' => ['linked_at', 'datetime_immutable', null, false, [], false],
                'lastUsedAt' => ['last_used_at', 'datetime_immutable', null, true, [], false],
            ],
            'indexes' => ['phil_identity_linked_account' => ['account_id']],
            'unique' => ['phil_identity_linked_subject' => ['provider', 'subject']],
        ],
        'Passkey' => [
            'table' => 'phil_identity_passkey',
            'fields' => [
                'id' => ['id', 'string', 64, false, ['fixed' => true], true],
                'accountId' => ['account_id', 'string', 26, false, ['fixed' => true], false],
                'credentialId' => ['credential_id', 'text', null, false, [], false],
                'coseKey' => ['cose_key', 'text', null, false, [], false],
                'aaguid' => ['aaguid', 'string', 36, false, ['fixed' => true], false],
                'name' => ['name', 'string', 100, false, [], false],
                'signCount' => ['sign_count', 'bigint', null, false, [], false],
                'backupEligible' => ['backup_eligible', 'boolean', null, false, [], false],
                'backedUp' => ['backed_up', 'boolean', null, false, [], false],
                'createdAt' => ['created_at', 'datetime_immutable', null, false, [], false],
                'lastUsedAt' => ['last_used_at', 'datetime_immutable', null, true, [], false],
            ],
            'indexes' => ['phil_identity_passkey_account' => ['account_id']],
            'unique' => [],
        ],
        'RecoveryCodeSet' => [
            'table' => 'phil_identity_recovery_codes',
            'fields' => [
                'accountId' => ['account_id', 'string', 26, false, ['fixed' => true], true],
                'hashes' => ['hashes', 'json', null, false, [], false],
                'createdAt' => ['created_at', 'datetime_immutable', null, false, [], false],
            ],
            'indexes' => [],
            'unique' => [],
        ],
        'SigningKey' => [
            'table' => 'phil_identity_signing_key',
            'fields' => [
                'kid' => ['kid', 'string', 64, false, [], true],
                'algorithm' => ['algorithm', 'string', 10, false, [], false],
                'privateMaterial' => ['private_material', 'text', null, false, [], false],
                'publicJwk' => ['public_jwk', 'json', null, false, [], false],
                'createdAt' => ['created_at', 'datetime_immutable', null, false, [], false],
                'activatedAt' => ['activated_at', 'datetime_immutable', null, true, [], false],
                'retiredAt' => ['retired_at', 'datetime_immutable', null, true, [], false],
            ],
            'indexes' => [],
            'unique' => [],
        ],
        'TotpCredential' => [
            'table' => 'phil_identity_totp',
            'fields' => [
                'accountId' => ['account_id', 'string', 26, false, ['fixed' => true], true],
                'secret' => ['secret', 'text', null, false, [], false],
                'createdAt' => ['created_at', 'datetime_immutable', null, false, [], false],
                'confirmedAt' => ['confirmed_at', 'datetime_immutable', null, true, [], false],
                'lastCounter' => ['last_counter', 'bigint', null, true, [], false],
            ],
            'indexes' => [],
            'unique' => [],
        ],
        'UsedAssertion' => [
            'table' => 'phil_identity_assertion',
            'fields' => [
                'id' => ['id', 'string', 64, false, ['fixed' => true], true],
                'expiresAt' => ['expires_at', 'datetime_immutable', null, false, [], false],
            ],
            'indexes' => ['phil_identity_assertion_expires_at' => ['expires_at']],
            'unique' => [],
        ],
    ];

    public function testTheTablesAreMappedAsDocumented(): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);
        $actual = [];
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        // The attributes of a model sit on its constructor: building one is what makes a test cover them.
        $models = [
            Account::register('01JCZ6Q7Y3W0000000000000AA', 'ada@example.com', $now),
            AccountSession::open(str_repeat('a', 43), '01JCZ6Q7Y3W0000000000000AA', 'Device', [], false, $now),
            AuthorizationCode::issue('code', 'app', '01JCZ6Q7Y3W0000000000000AA', 'https://a/cb', ['openid'], null, 'challenge', $now, [], null, 'FAMILY0000000000000000000A', $now),
            Client::register('app', 'App', null, ['https://a/cb'], [], [], false, $now),
            Consent::grant('01JCZ6Q7Y3W0000000000000AA', 'app', ['openid'], $now),
            IssuedToken::issue('jti', TokenKind::Access, 'FAMILY0000000000000000000A', 'app', '01JCZ6Q7Y3W0000000000000AA', [], null, $now, [], $now, $now),
            LinkedIdentity::link('01JCZ6Q7Y3W0000000000000AA', 'google', 'g-1', $now),
            Passkey::register('01JCZ6Q7Y3W0000000000000AA', new RegisteredCredential('one', 'c', 0, str_repeat("\x01", 16), false, false, false), 'One', $now),
            RecoveryCodeSet::generate('01JCZ6Q7Y3W0000000000000AA', ['a'], $now),
            SigningKey::create('kid', SigningAlgorithm::ES256, 'p', ['kid' => 'kid'], $now),
            TotpCredential::enroll('01JCZ6Q7Y3W0000000000000AA', 'secret', $now),
            UsedAssertion::record('app', 'jti', $now),
        ];
        self::assertCount(12, $models);

        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $fields = [];

            foreach ($metadata->fieldMappings as $name => $mapping) {
                $fields[$name] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable ?? false, $mapping->options ?? [], $mapping->id ?? false];
            }

            $short = new ReflectionClass($metadata->getName())->getShortName();
            $actual[$short] = [
                'table' => $metadata->getTableName(),
                'fields' => $fields,
                'indexes' => $this->columns($metadata->table['indexes'] ?? []),
                'unique' => $this->columns($metadata->table['uniqueConstraints'] ?? []),
            ];
        }

        ksort($actual);
        self::assertSame(self::MAPPING, $actual);
    }

    /**
     * @param array<array-key, mixed> $definitions
     *
     * @return array<array-key, mixed>
     */
    private function columns(array $definitions): array
    {
        $columns = [];

        foreach ($definitions as $name => $definition) {
            $columns[$name] = \is_array($definition) ? ($definition['columns'] ?? $definition) : $definition;
        }

        return $columns;
    }
}
