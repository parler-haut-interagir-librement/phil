<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\RateLimiter;

use Override;
use Phil\NewsletterBundle\Application\Contract\ConfirmationThrottle;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * The limits of the public subscription form, on by default: each subscription may send a confirmation to an address
 * whose owner never asked for it, and spends the sender's quota and reputation.
 *
 * - per network address, the client's address or its IPv6 /64 network: how many subscriptions one client posts;
 * - per address typed, on each site, whatever the client: how often one address is subscribed, subscribed already or
 *   not, so that the answer tells nobody who is on a list;
 * - the delay before another confirmation goes to the same subscriber.
 *
 * The address typed is trimmed and lowercased, so that its variants share their counts. The keys are keyed hashes: the
 * store never holds an address or an email address.
 */
final readonly class SubscriptionRateLimiter implements ConfirmationThrottle
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
     * Whether the client may subscribe this address on this site now; a refusal by the client's count spends nothing
     * of the address's.
     */
    public function allowsSubscription(Request $request, string $site, #[SensitiveParameter] string $email): bool
    {
        $address = $request->getClientIp() ?? '';
        $network = IpUtils::checkIp($address, '::/0') ? IpUtils::anonymize($address, 0, 8) : $address;

        return $this->perIp->create($this->key($network))->consume()->isAccepted()
            && $this->perAddress->create($this->key($site."\n".mb_strtolower(trim($email))))->consume()->isAccepted();
    }

    #[Override]
    public function allowsConfirmation(string $subscriberId): bool
    {
        return $this->resend->create($this->key($subscriberId))->consume()->isAccepted();
    }

    /**
     * A keyed hash of the part; each factory adds its own id before it, so the counts never share a key.
     */
    private function key(string $part): string
    {
        return sodium_bin2base64(hash_hmac('sha256', $part, $this->secret, true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }
}
