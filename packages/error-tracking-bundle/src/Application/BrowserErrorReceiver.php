<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Application;

use Phil\ErrorTracking\CaptureContext;
use Phil\ErrorTracking\Contract\CaptureThrottle;
use Phil\ErrorTracking\EventFactory;
use Phil\ErrorTracking\RequestSummary;
use Phil\ErrorTracking\Severity;
use Phil\ErrorTracking\Source;
use Phil\ErrorTrackingBundle\Application\Exception\InvalidBrowserError;
use Phil\IpAnonymizer\Contract\IpAnonymizer;
use SensitiveParameter;

/**
 * Receives what a browser posted to the endpoint, in this order: its media type, its size, its origin, its token, the
 * throttle of its client, its report. A report read becomes an event of the `browser` source, with the page as the
 * request's address, the browser's `User-Agent` as its only header, and the client's address as the anonymizer leaves
 * it; the tracker then scrubs it like any other.
 *
 * The origin is the defence against a page of another site posting in the visitor's name: the `Origin` header must be
 * one of the configured origins, or the endpoint's own when none is configured. A token, when one is configured, is
 * compared in constant time. The throttle counts a keyed digest of the client's address, never the address.
 */
final readonly class BrowserErrorReceiver
{
    /** The usage label the key is derived under, by HKDF-SHA256, from the secret given. */
    public const string USAGE = 'phil_error_tracking.browser_throttle';

    /** The key of this usage alone: no other usage of the same secret gives the same hash. */
    private string $key;

    public const int DEFAULT_MAX_BYTES = 16_384;

    /**
     * @param list<string> $origins the origins allowed to post, `https://example.com`; the endpoint's own when empty
     */
    public function __construct(
        private BrowserErrorParser $parser,
        private EventFactory $factory,
        private SafeCapture $capture,
        private IpAnonymizer $anonymizer,
        #[SensitiveParameter]
        string $secret,
        private int $maxBytes = self::DEFAULT_MAX_BYTES,
        private array $origins = [],
        #[SensitiveParameter]
        private ?string $token = null,
        private ?CaptureThrottle $throttle = null,
    ) {
        $this->key = hash_hkdf('sha256', $secret, 32, self::USAGE);
    }

    public function maxBytes(): int
    {
        return $this->maxBytes;
    }

    /**
     * @param string  $ownOrigin the endpoint's own origin, allowed when no origin is configured
     * @param ?string $token     the token the browser sent, if any
     */
    public function receive(
        string $contentType,
        string $body,
        ?string $origin,
        string $ownOrigin,
        #[SensitiveParameter]
        ?string $token,
        #[SensitiveParameter]
        ?string $clientAddress,
        ?string $userAgent,
    ): Receipt {
        if (!\in_array(strtolower(trim(explode(';', $contentType)[0])), ['application/json', 'text/plain'], true)) {
            return Receipt::UnsupportedType;
        }

        if (\strlen($body) > $this->maxBytes) {
            return Receipt::TooLarge;
        }

        if (null === $origin || !\in_array(strtolower($origin), [] === $this->origins ? [strtolower($ownOrigin)] : $this->origins, true)) {
            return Receipt::Forbidden;
        }

        if (null !== $this->token && !hash_equals($this->token, $token ?? '')) {
            return Receipt::Forbidden;
        }

        if ($this->throttle instanceof CaptureThrottle && !$this->throttle->allow(hash_hmac('sha256', $clientAddress ?? '', $this->key))) {
            return Receipt::TooManyRequests;
        }

        try {
            $error = $this->parser->parse($body);
        } catch (InvalidBrowserError) {
            return Receipt::Malformed;
        }

        $request = RequestSummary::anonymized('GET', $error->url, null, null === $userAgent ? [] : ['user-agent' => $userAgent], $clientAddress, $this->anonymizer);
        $this->capture->event($this->factory->fromMessage($error->type, $error->message, $error->frames, new CaptureContext(Source::Browser, Severity::Error, $request)));

        return Receipt::Accepted;
    }
}
