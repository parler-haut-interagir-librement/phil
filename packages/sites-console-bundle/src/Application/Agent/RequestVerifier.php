<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Agent;

use Phil\SitesConsoleBundle\Application\Contract\NonceStore;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Exception\SignatureRefused;
use Phil\SitesConsoleBundle\Domain\Signature\Protocol;
use Phil\SitesConsoleBundle\Domain\Signature\VerifyingKey;
use Psr\Clock\ClockInterface;

/**
 * Checks, on the agent, that a request comes from the console, for this site and this action, now, and only once:
 * the site named, the date within the allowed skew, the nonce well formed, the signature verified with a key of the
 * console the site accepts — the current one, or the next one during a rotation — and only then the nonce remembered,
 * so that nobody without a key can fill the store.
 */
final readonly class RequestVerifier
{
    public const int DEFAULT_MAX_SKEW = 300;

    public function __construct(
        private string $siteId,
        private ConsoleKeyRing $consoleKeys,
        private NonceStore $nonces,
        private ClockInterface $clock,
        private int $maxSkew = self::DEFAULT_MAX_SKEW,
    ) {
        if ($maxSkew < 1 || $maxSkew > 3600) {
            throw InvalidValue::because('The allowed clock skew is 1 to 3600 seconds.');
        }
    }

    /**
     * @return VerifiedRequest the nonce, which the response signs back, and the key that signed the request
     *
     * @throws SignatureRefused
     */
    public function verify(string $action, ?string $site, ?string $date, ?string $nonce, ?string $signature, string $body): VerifiedRequest
    {
        if (\in_array(null, [$site, $date, $nonce, $signature], true)) {
            throw SignatureRefused::because('A header of the signature is missing.');
        }

        if (!hash_equals($this->siteId, $site)) {
            throw SignatureRefused::because('The request is meant for another site.');
        }

        if (1 !== preg_match('/^\d{1,12}$/D', $date) || abs((int) $date - $this->clock->now()->getTimestamp()) > $this->maxSkew) {
            throw SignatureRefused::because('The date of the request is outside the allowed window.');
        }

        if (!Protocol::isNonce($nonce)) {
            throw SignatureRefused::because('The nonce is malformed.');
        }

        $signer = $this->consoleKeys->keys()->signer(Protocol::requestMessage($site, $action, (int) $date, $nonce, $body), $signature);

        if (!$signer instanceof VerifyingKey) {
            throw SignatureRefused::because('The signature does not verify with a key of the console.');
        }

        if (!$this->nonces->remember($nonce, 2 * $this->maxSkew)) {
            throw SignatureRefused::because('The nonce was seen before.');
        }

        return new VerifiedRequest($nonce, $signer);
    }
}
