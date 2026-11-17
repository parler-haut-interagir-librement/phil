<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

use Override;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\RateLimiter\AbstractRequestRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * The limiter of the firewall's `login_throttling`, set by its option `limiter`: three counts for each sign-in.
 *
 * - per network address, the client's address or its IPv6 /64 network: how many identifiers one client tries;
 * - per identifier and network address: how often one client guesses for one account;
 * - per identifier, whatever the address: how often one account is guessed for by clients spread over many
 *   addresses, which the two counts above never see.
 *
 * The identifier is trimmed and lowercased as the accounts find an address, so that its variants share their counts. The
 * keys are keyed hashes: the store never holds an address or an identifier. A sign-in that succeeds clears both counts
 * of its identifier and gives its token back to its address, which keeps what the other identifiers spent.
 *
 * The count per identifier lets anyone hold back the sign-ins of an account whose address they know by failing on
 * purpose: its limit is set higher than the others, and lasts only its interval.
 */
final class LoginRateLimiter extends AbstractRequestRateLimiter
{
    public function __construct(
        private readonly RateLimiterFactoryInterface $network,
        private readonly RateLimiterFactoryInterface $local,
        private readonly RateLimiterFactoryInterface $account,
        #[SensitiveParameter]
        private readonly string $secret,
    ) {
    }

    #[Override]
    public function reset(Request $request): void
    {
        $identifier = $this->identifier($request);

        if (null !== $identifier) {
            $this->localLimiter($request, $identifier)->reset();
            $this->accountLimiter($identifier)->reset();
        }

        $this->networkLimiter($request)->consume(-1);
    }

    /**
     * @return list<LimiterInterface>
     */
    #[Override]
    protected function getLimiters(Request $request): array
    {
        $identifier = $this->identifier($request);

        if (null === $identifier) {
            return [$this->networkLimiter($request)];
        }

        return [$this->networkLimiter($request), $this->localLimiter($request, $identifier), $this->accountLimiter($identifier)];
    }

    private function networkLimiter(Request $request): LimiterInterface
    {
        return $this->network->create($this->key($this->network($request)));
    }

    private function localLimiter(Request $request, string $identifier): LimiterInterface
    {
        return $this->local->create($this->key($identifier, $this->network($request)));
    }

    private function accountLimiter(string $identifier): LimiterInterface
    {
        return $this->account->create($this->key($identifier));
    }

    private function identifier(Request $request): ?string
    {
        $identifier = $request->attributes->get(SecurityRequestAttributes::LAST_USERNAME);

        if (!\is_string($identifier) || '' === $identifier = trim($identifier)) {
            return null;
        }

        return mb_strtolower($identifier);
    }

    private function network(Request $request): string
    {
        $address = $request->getClientIp() ?? '';

        return IpUtils::checkIp($address, '::/0') ? IpUtils::anonymize($address, 0, 8) : $address;
    }

    /**
     * A keyed hash of the parts; each factory adds its own id before it, so the three counts never share a key.
     */
    private function key(string ...$parts): string
    {
        return sodium_bin2base64(hash_hmac('sha256', implode("\0", $parts), $this->secret, true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }
}
