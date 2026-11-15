<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Model\Oidc;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use SensitiveParameter;

/**
 * A client of the OpenID Connect provider, as a row of `phil_identity_client`: its identifier, the SHA-256 of its
 * secret — none for a public client, such as a single-page or mobile application, which proves itself by PKCE
 * alone —, its name, the exact redirect and post-logout addresses it registered, the scopes it may ask for, whether
 * it is a first-party application that needs no consent, whether its authorization requests must carry a PKCE
 * challenge — always for a public client, by default for a confidential one —, and when it was disabled.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final class Client
{
    public const string TABLE = 'phil_identity_client';

    public const string ID_PATTERN = '/^[A-Za-z0-9_.-]{3,64}$/D';

    public const int NAME_MAX_LENGTH = 100;

    /**
     * @param list<string> $redirectUris
     * @param list<string> $postLogoutRedirectUris
     * @param list<string> $scopes
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
    ) {
    }

    /**
     * @param list<string> $redirectUris           the exact addresses codes are sent back to, already checked
     * @param list<string> $postLogoutRedirectUris
     * @param list<string> $scopes                 the scopes it may ask for; `openid` is added
     * @param bool         $requirePkce            whether its authorization requests must carry a PKCE challenge;
     *                                             a public client always must
     */
    public static function register(string $id, string $name, #[SensitiveParameter] ?string $secret, array $redirectUris, array $postLogoutRedirectUris, array $scopes, bool $firstParty, DateTimeImmutable $now, bool $requirePkce = true): self
    {
        if (null === $secret && !$requirePkce) {
            throw InvalidValue::because('A public client proves itself by PKCE: it cannot go without.');
        }

        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw InvalidValue::because('A client identifier is 3 to 64 letters, digits, "_", "." or "-".');
        }

        if ([] === $redirectUris) {
            throw InvalidValue::because('A client registers at least one redirect address.');
        }

        $client = new self($id, '', null === $secret ? null : self::hash($secret), array_values(array_unique($redirectUris)), array_values(array_unique($postLogoutRedirectUris)), array_values(array_unique(['openid', ...$scopes])), $firstParty, $now, null, $requirePkce);
        $client->rename($name);

        return $client;
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

    public function isPublic(): bool
    {
        return null === $this->secretHash;
    }

    public function verifySecret(#[SensitiveParameter] string $secret): bool
    {
        return null !== $this->secretHash && hash_equals($this->secretHash, self::hash($secret));
    }

    public function rotateSecret(#[SensitiveParameter] string $secret): void
    {
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
