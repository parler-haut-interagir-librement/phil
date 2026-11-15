<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application;

use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
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
 * password over its hash and when it was last changed. Each one stops working once used, with nothing stored.
 */
final readonly class SignedLinks
{
    public const string VERIFY = 'verify';

    public const string RESET = 'reset';

    private const array PROPERTIES = [self::VERIFY => ['email', 'emailVerifiedAt'], self::RESET => ['password', 'passwordChangedAt']];

    public function __construct(
        #[SensitiveParameter]
        private string $secret,
        private ClockInterface $clock,
        private int $verifyLifetime = 86_400,
        private int $resetLifetime = 3_600,
    ) {
    }

    /**
     * The expiry and the signature of a link of the purpose.
     *
     * @return array{expires: int, signature: string}
     */
    public function sign(string $purpose, Account $account): array
    {
        $expires = $this->clock->now()->getTimestamp() + (self::RESET === $purpose ? $this->resetLifetime : $this->verifyLifetime);

        return ['expires' => $expires, 'signature' => $this->hasher($purpose)->computeSignatureHash(new AccountUser($account), $expires)];
    }

    /**
     * Whether a link of the purpose is still valid for the account.
     */
    public function verify(string $purpose, Account $account, int $expires, string $signature): bool
    {
        if ($expires < $this->clock->now()->getTimestamp()) {
            return false;
        }

        try {
            $this->hasher($purpose)->verifySignatureHash(new AccountUser($account), $expires, $signature);
        } catch (InvalidSignatureException|ExpiredSignatureException) {
            return false;
        }

        return true;
    }

    private function hasher(string $purpose): SignatureHasher
    {
        return new SignatureHasher(PropertyAccess::createPropertyAccessor(), self::PROPERTIES[$purpose] ?? self::PROPERTIES[self::VERIFY], hash_hmac('sha256', 'phil_identity_provider.'.$purpose, $this->secret));
    }
}
