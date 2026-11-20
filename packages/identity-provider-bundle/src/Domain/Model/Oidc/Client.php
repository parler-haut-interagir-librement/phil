<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Model\Oidc;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Oidc\ClientAuthMethod;
use SensitiveParameter;

/**
 * A client of the OpenID Connect provider, as a row of `phil_identity_client`: its identifier, the SHA-256 of its
 * secret — none for a public client, such as a single-page or mobile application, which proves itself by PKCE
 * alone —, its name, the exact redirect and post-logout addresses it registered, the scopes it may ask for, whether
 * it is a first-party application that needs no consent, whether its authorization requests must carry a PKCE
 * challenge — always for a public client, by default for a confidential one —, and when it was disabled.
 *
 * A confidential client proves itself by its secret or, in its place, by a JSON Web Token signed with a private key
 * whose public JSON Web Key it registered (`private_key_jwt`, RFC 7523): its key set holds public keys only, each with
 * its identifier and one algorithm of the provider. The resources it may ask tokens for (RFC 8707) are the audiences
 * of those tokens; a client that only asks tokens for itself, by the `client_credentials` grant, registers resources
 * and no redirect address. A client may register the address the provider posts its logout tokens to when a session
 * ends.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final class Client
{
    public const string TABLE = 'phil_identity_client';

    public const string ID_PATTERN = '/^[A-Za-z0-9_.-]{3,64}$/D';

    public const int NAME_MAX_LENGTH = 100;

    /** The most public keys a client registers: the current one and the next, with room for a rotation. */
    public const int MAX_KEYS = 5;

    /** The most resources a client may ask tokens for. */
    public const int MAX_RESOURCES = 20;

    /** The members a public JSON Web Key of a client may hold; any other, such as a private member, is refused. */
    private const array KEY_MEMBERS = ['kty', 'kid', 'alg', 'use', 'n', 'e', 'crv', 'x', 'y'];

    /**
     * @param list<string>                     $redirectUris
     * @param list<string>                     $postLogoutRedirectUris
     * @param list<string>                     $scopes
     * @param list<array<string, string>>|null $jwks
     * @param list<string>|null                $resources
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 64)]
        private readonly string $id,
        #[ORM\Column(name: 'name', length: self::NAME_MAX_LENGTH)]
        private string $name,
        #[ORM\Column(name: 'secret_hash', length: 64, nullable: true, options: ['fixed' => true])]
        private ?string $secretHash,
        #[ORM\Column(name: 'redirect_uris', type: 'json')]
        private array $redirectUris,
        #[ORM\Column(name: 'post_logout_redirect_uris', type: 'json')]
        private array $postLogoutRedirectUris,
        #[ORM\Column(name: 'scopes', type: 'json')]
        private array $scopes,
        #[ORM\Column(name: 'first_party')]
        private bool $firstParty,
        #[ORM\Column(name: 'created_at')]
        private readonly DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'disabled_at', nullable: true)]
        private ?DateTimeImmutable $disabledAt,
        #[ORM\Column(name: 'require_pkce', options: ['default' => true])]
        private bool $requirePkce,
        #[ORM\Column(name: 'jwks', type: 'json', nullable: true)]
        private ?array $jwks = null,
        #[ORM\Column(name: 'resources', type: 'json', nullable: true)]
        private ?array $resources = null,
        #[ORM\Column(name: 'backchannel_logout_uri', type: 'text', nullable: true)]
        private ?string $backchannelLogoutUri = null,
        #[ORM\Column(name: 'sector_identifier', length: 253, nullable: true)]
        private ?string $sectorIdentifier = null,
    ) {
    }

    /**
     * @param list<string>                    $redirectUris           the exact addresses codes are sent back to, already checked
     * @param list<string>                    $postLogoutRedirectUris
     * @param list<string>                    $scopes                 the scopes it may ask for; `openid` is added
     * @param bool                            $requirePkce            whether its authorization requests must carry a PKCE challenge;
     *                                                                a public client always must
     * @param list<array<string, mixed>>|null $jwks                   the public keys of `private_key_jwt`, in place of a secret
     * @param list<string>                    $resources              the audiences it may ask tokens for, already checked
     * @param ?string                         $backchannelLogoutUri   where its logout tokens are posted, already checked
     */
    public static function register(string $id, string $name, #[SensitiveParameter] ?string $secret, array $redirectUris, array $postLogoutRedirectUris, array $scopes, bool $firstParty, DateTimeImmutable $now, bool $requirePkce = true, ?array $jwks = null, array $resources = [], ?string $backchannelLogoutUri = null, ?string $sectorIdentifier = null): self
    {
        if (null !== $sectorIdentifier && 1 !== preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/D', $sectorIdentifier)) {
            throw InvalidValue::because('A sector identifier is a host name in lower case.');
        }

        if (null !== $secret && null !== $jwks) {
            throw InvalidValue::because('A client proves itself by its secret or by its keys, not both.');
        }

        if (null === $secret && null === $jwks && !$requirePkce) {
            throw InvalidValue::because('A public client proves itself by PKCE: it cannot go without.');
        }

        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw InvalidValue::because('A client identifier is 3 to 64 letters, digits, "_", "." or "-".');
        }

        $resources = array_values(array_unique($resources));

        if (\count($resources) > self::MAX_RESOURCES) {
            throw InvalidValue::because(\sprintf('A client registers at most %d resources.', self::MAX_RESOURCES));
        }

        if ([] === $redirectUris && ([] === $resources || null === $secret && null === $jwks)) {
            throw InvalidValue::because('A client registers at least one redirect address, or, confidential, the resources it asks tokens for.');
        }

        $client = new self($id, '', null === $secret ? null : self::hash($secret), array_values(array_unique($redirectUris)), array_values(array_unique($postLogoutRedirectUris)), array_values(array_unique(['openid', ...$scopes])), $firstParty, $now, null, $requirePkce, null === $jwks ? null : self::keySet($jwks), [] === $resources ? null : $resources, $backchannelLogoutUri, $sectorIdentifier);
        $client->rename($name);

        return $client;
    }

    /**
     * The public keys of a client, checked: at most MAX_KEYS, each an RSA key of at least 2048 bits, a P-256 key or an
     * Ed25519 key, with a distinct identifier and the algorithm of its type, for signing, and no private member.
     *
     * @param list<array<string, mixed>> $keys
     *
     * @return list<array<string, string>>
     *
     * @throws InvalidValue
     */
    public static function keySet(array $keys): array
    {
        if ([] === $keys || \count($keys) > self::MAX_KEYS) {
            throw InvalidValue::because(\sprintf('A client registers 1 to %d public keys.', self::MAX_KEYS));
        }

        $set = [];

        foreach ($keys as $key) {
            $key = self::checkedKey($key);

            if (\array_key_exists($key['kid'], $set)) {
                throw InvalidValue::because('The keys of a client have distinct identifiers.');
            }

            $set[$key['kid']] = $key;
        }

        return array_values($set);
    }

    /**
     * @param array<string, mixed> $key
     *
     * @return array<string, string>
     */
    private static function checkedKey(array $key): array
    {
        $strings = array_filter($key, is_string(...));

        if (\count($strings) !== \count($key) || [] !== array_diff(array_keys($key), self::KEY_MEMBERS)) {
            throw InvalidValue::because('A public key of a client holds text members of a public key only: '.implode(', ', self::KEY_MEMBERS).'.');
        }

        $algorithm = SigningAlgorithm::tryFrom($strings['alg'] ?? '');
        $kid = $strings['kid'] ?? '';

        if (!$algorithm instanceof SigningAlgorithm || 1 !== preg_match('/^[\x21-\x7e]{1,128}$/D', $kid) || $algorithm->keyType() !== ($strings['kty'] ?? null) || 'sig' !== ($strings['use'] ?? 'sig')) {
            throw InvalidValue::because('A public key of a client names its identifier, its type and the algorithm of its type, for signing.');
        }

        [$curve, $members] = match ($algorithm) {
            SigningAlgorithm::RS256 => [null, ['n', 'e']],
            SigningAlgorithm::ES256 => ['P-256', ['x', 'y']],
            SigningAlgorithm::EdDSA => ['Ed25519', ['x']],
        };

        if ($curve !== ($strings['crv'] ?? null) || [] !== array_diff(array_keys($strings), ['kty', 'kid', 'alg', 'use', 'crv', ...$members]) || \count(array_intersect(array_keys($strings), $members)) !== \count($members)) {
            throw InvalidValue::because(\sprintf('A public key of %s holds %s.', $algorithm->value, implode(', ', $members)));
        }

        try {
            $modulus = SigningAlgorithm::RS256 === $algorithm ? Base64Url::decode($strings['n']) : '';
        } catch (MalformedInput) {
            throw InvalidValue::because('The modulus of a public key is in base64url.');
        }

        if (SigningAlgorithm::RS256 === $algorithm && 8 * \strlen(ltrim($modulus, "\x00")) < 2048) {
            throw InvalidValue::because('An RSA public key has a modulus of at least 2048 bits.');
        }

        return $strings;
    }

    public static function hash(#[SensitiveParameter] string $secret): string
    {
        return hash('sha256', $secret);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $name = trim($name);

        if ('' === $name || mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw InvalidValue::because('The name of a client holds 1 to 100 characters.');
        }

        $this->name = $name;
    }

    /**
     * Whether the client proves itself by PKCE alone: no secret and no key.
     */
    public function isPublic(): bool
    {
        return null === $this->secretHash && null === $this->jwks;
    }

    /**
     * Whether the client may authenticate by the method: a public client by none, a client with a secret by it in HTTP
     * Basic or in the body, a client with keys by a JSON Web Token they sign.
     */
    public function accepts(ClientAuthMethod $method): bool
    {
        return match (true) {
            null !== $this->jwks => ClientAuthMethod::PrivateKeyJwt === $method,
            null !== $this->secretHash => $method->usesSecret(),
            default => ClientAuthMethod::None === $method,
        };
    }

    /**
     * The public key of `private_key_jwt` its identifier names, as registered.
     *
     * @return array<string, string>|null
     */
    public function publicKey(string $kid): ?array
    {
        return array_find($this->jwks ?? [], static fn (array $key): bool => $key['kid'] === $kid);
    }

    /**
     * @return list<array<string, string>>
     */
    public function jwks(): array
    {
        return $this->jwks ?? [];
    }

    /**
     * Replaces the public keys of a client that authenticates by them, for a rotation.
     *
     * @param list<array<string, mixed>> $keys
     *
     * @throws InvalidValue when the client has no keys, or a key is refused
     */
    public function replaceJwks(array $keys): void
    {
        if (null === $this->jwks) {
            throw InvalidValue::because('Only a client that authenticates by its keys has keys to replace.');
        }

        $this->jwks = self::keySet($keys);
    }

    /**
     * @return list<string> the audiences it may ask tokens for
     */
    public function resources(): array
    {
        return $this->resources ?? [];
    }

    /**
     * Where its logout tokens are posted when a session it has tokens of ends; null for none (OpenID Connect
     * Back-Channel Logout 1.0).
     */
    public function backchannelLogoutUri(): ?string
    {
        return $this->backchannelLogoutUri;
    }

    /**
     * The sector its pairwise subjects are derived for: the host it registered, or its own identifier.
     */
    public function sector(): string
    {
        return $this->sectorIdentifier ?? $this->id;
    }

    public function allowsResource(string $resource): bool
    {
        return \in_array($resource, $this->resources ?? [], true);
    }

    public function verifySecret(#[SensitiveParameter] string $secret): bool
    {
        return null !== $this->secretHash && hash_equals($this->secretHash, self::hash($secret));
    }

    /**
     * @throws InvalidValue when the client has no secret: a public client, or one that authenticates by its keys
     */
    public function rotateSecret(#[SensitiveParameter] string $secret): void
    {
        if (null === $this->secretHash) {
            throw InvalidValue::because('Only a client that has a secret can rotate it.');
        }

        $this->secretHash = self::hash($secret);
    }

    /**
     * @return list<string>
     */
    public function redirectUris(): array
    {
        return $this->redirectUris;
    }

    public function allowsRedirect(string $uri): bool
    {
        return \in_array($uri, $this->redirectUris, true);
    }

    /**
     * @return list<string>
     */
    public function postLogoutRedirectUris(): array
    {
        return $this->postLogoutRedirectUris;
    }

    public function allowsPostLogoutRedirect(string $uri): bool
    {
        return \in_array($uri, $this->postLogoutRedirectUris, true);
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    /**
     * Whether an authorization request of the client without a PKCE challenge is refused. A confidential client that
     * goes without relies on its secret and on the `nonce` of its requests against an injected code.
     */
    public function requiresPkce(): bool
    {
        return $this->requirePkce;
    }

    public function isFirstParty(): bool
    {
        return $this->firstParty;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isDisabled(): bool
    {
        return $this->disabledAt instanceof DateTimeImmutable;
    }

    public function disable(DateTimeImmutable $now): void
    {
        $this->disabledAt ??= $now;
    }
}
