<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

use SensitiveParameter;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * The limits of registration, on by default: each one costs a password hash, a row and an email.
 *
 * - per network address, the client's address or its IPv6 /64 network: how many accounts one client asks for;
 * - per address typed, whatever the client: how often one address is registered, known or not, so that the answer
 *   tells nobody who has an account;
 * - the delay before another verification email goes to the same address, by registration, by the account page or by
 *   a change of address: a link already sent is not sent again and again to someone who never asked for it.
 *
 * The address typed is trimmed and lowercased, so that its variants share their counts. The keys are keyed hashes: the
 * store never holds an address or an email address.
 */
final readonly class RegistrationRateLimiter
{
    public function __construct(
        private RateLimiterFactoryInterface $perIp,
        private RateLimiterFactoryInterface $perAddress,
        private RateLimiterFactoryInterface $resend,
        #[SensitiveParameter]
        private string $secret,
    ) {
    }

    /**
     * Whether the client may register this address now; a refusal by the client's count spends nothing of the address's.
     */
    public function allowsRegistration(Request $request, #[SensitiveParameter] string $email): bool
    {
        $address = $request->getClientIp() ?? '';
        $network = IpUtils::checkIp($address, '::/0') ? IpUtils::anonymize($address, 0, 8) : $address;

        return $this->perIp->create($this->key($network))->consume()->isAccepted()
            && $this->perAddress->create($this->key($this->normalized($email)))->consume()->isAccepted();
    }

    /**
     * Whether a verification email may go to this address now: once in each `resend_delay`.
     */
    public function allowsVerificationEmail(#[SensitiveParameter] string $email): bool
    {
        return $this->resend->create($this->key($this->normalized($email)))->consume()->isAccepted();
    }

    private function normalized(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * A keyed hash of the part; each factory adds its own id before it, so the counts never share a key.
     */
    private function key(string $part): string
    {
        return sodium_bin2base64(hash_hmac('sha256', $part, $this->secret, true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }
}
