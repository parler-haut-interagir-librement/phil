<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Domain\Contract\LinkTokenRepository;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Link\LinkToken;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\Signature\Exception\ExpiredSignatureException;
use Symfony\Component\Security\Core\Signature\Exception\InvalidSignatureException;
use Symfony\Component\Security\Core\Signature\SignatureHasher;

/**
 * The signatures of the links sent by email, through the `SignatureHasher` of Symfony Security, the one its login
 * links use: an HMAC over the account, the expiry and properties of the account, under a secret of its own for each
 * purpose. A link that verifies an address is signed over the address and when it was verified; a link that resets a
 * password over its hash and when it was last changed. Each one stops working once used.
 *
 * In the signed mode, the default, nothing is stored: a link works until it expires or the properties it was signed
 * over change. In the stored mode — with a `LinkTokenRepository` — the link carries a random token instead, and the
 * store keeps its keyed hash with the account, the purpose, the expiry and the signature of the properties as a
 * fingerprint: the link also stops working once consumed or revoked, and a copy of the store tells nothing that opens
 * an account.
 */
final readonly class SignedLinks
{
    public const string VERIFY = 'verify';

    public const string RESET = 'reset';

    public const int TOKEN_BYTES = 32;

    private const array PROPERTIES = [self::VERIFY => ['email', 'emailVerifiedAt'], self::RESET => ['password', 'passwordChangedAt']];

    public function __construct(
        #[SensitiveParameter]
        private string $secret,
        private ClockInterface $clock,
        private int $verifyLifetime = 86_400,
        private int $resetLifetime = 3_600,
        private ?LinkTokenRepository $tokens = null,
    ) {
    }

    /**
     * Whether the links carry a stored token rather than a signature.
     */
    public function isStored(): bool
    {
        return $this->tokens instanceof LinkTokenRepository;
    }

    /**
     * The expiry and the signature of a link of the purpose; in the stored mode, the signature is the token.
     *
     * @return array{expires: int, signature: string}
     */
    public function sign(string $purpose, Account $account): array
    {
        $now = $this->clock->now();
        $expires = $now->getTimestamp() + (self::RESET === $purpose ? $this->resetLifetime : $this->verifyLifetime);
        $signature = $this->hasher($purpose)->computeSignatureHash(new AccountUser($account), $expires);

        if (!$this->tokens instanceof LinkTokenRepository) {
            return ['expires' => $expires, 'signature' => $signature];
        }

        $token = Base64Url::encode(random_bytes(self::TOKEN_BYTES));
        $this->tokens->save(LinkToken::issue($this->tokenId($token), $account->id(), $purpose, $signature, new DateTimeImmutable('@'.$expires), $now));

        return ['expires' => $expires, 'signature' => $token];
    }

    /**
     * Whether a link of the purpose is still valid for the account.
     */
    public function verify(string $purpose, Account $account, int $expires, string $signature): bool
    {
        if ($expires < $this->clock->now()->getTimestamp()) {
            return false;
        }

        if ($this->tokens instanceof LinkTokenRepository) {
            $token = $this->tokens->get($this->tokenId($signature));

            if (!$token instanceof LinkToken || $token->accountId() !== $account->id() || $token->purpose() !== $purpose || $token->expiresAt()->getTimestamp() !== $expires) {
                return false;
            }

            $signature = $token->fingerprint();
        }

        try {
            $this->hasher($purpose)->verifySignatureHash(new AccountUser($account), $expires, $signature);
        } catch (InvalidSignatureException|ExpiredSignatureException) {
            return false;
        }

        return true;
    }

    /**
     * Uses a link up: in the stored mode, its token is removed, once; in the signed mode, the change the link leads to
     * is what ends it.
     *
     * @return bool false when another use of the link came first
     */
    public function consume(string $signature): bool
    {
        return !$this->tokens instanceof LinkTokenRepository || $this->tokens->consume($this->tokenId($signature));
    }

    /**
     * Revokes the links of an account still out, of one purpose or all of them; nothing in the signed mode.
     *
     * @return int how many were revoked
     */
    public function revoke(Account $account, ?string $purpose = null): int
    {
        return $this->tokens?->revoke($account->id(), $purpose) ?? 0;
    }

    /**
     * The keyed hash a token is stored under.
     */
    private function tokenId(string $token): string
    {
        return hash_hmac('sha256', $token, hash_hmac('sha256', 'phil_identity_provider.link_token', $this->secret));
    }

    private function hasher(string $purpose): SignatureHasher
    {
        return new SignatureHasher(PropertyAccess::createPropertyAccessor(), self::PROPERTIES[$purpose] ?? self::PROPERTIES[self::VERIFY], hash_hmac('sha256', 'phil_identity_provider.'.$purpose, $this->secret));
    }
}
