<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application;

use Phil\BrowserSecurityBundle\Application\Contract\ClientAddressAnonymizer;
use Phil\BrowserSecurityBundle\Application\Contract\ReportDeduplicator;
use Phil\BrowserSecurityBundle\Application\Contract\ReportThrottle;
use Phil\BrowserSecurityBundle\Application\Event\ViolationReported;
use Phil\BrowserSecurityBundle\Application\Exception\InvalidReport;
use Phil\BrowserSecurityBundle\Domain\Contract\ReportRepository;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SensitiveParameter;

/**
 * Receives the body a browser posted to the endpoint, in this order: its media type, its size, the throttle of its
 * client, its reports. Each report is kept once per body: stored, when a store is configured, and announced by a
 * `ViolationReported` event when the deduplicator has not seen it within its window.
 *
 * The client's address never leaves this class raw: the throttle counts a keyed digest of it, and the store and the
 * event get what the anonymizer makes of it, or nothing.
 */
final readonly class ReportReceiver
{
    public const int DEFAULT_MAX_BYTES = 65_536;

    public function __construct(
        private ReportParser $parser,
        private ClockInterface $clock,
        #[SensitiveParameter]
        private string $secret,
        private int $maxBytes = self::DEFAULT_MAX_BYTES,
        private ?ReportThrottle $throttle = null,
        private ?ReportDeduplicator $deduplicator = null,
        private ?ClientAddressAnonymizer $anonymizer = null,
        private ?ReportRepository $repository = null,
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    public function maxBytes(): int
    {
        return $this->maxBytes;
    }

    public function receive(string $contentType, string $body, #[SensitiveParameter] ?string $clientAddress): Receipt
    {
        if (!$this->parser->supports($contentType)) {
            return Receipt::UnsupportedType;
        }

        if (\strlen($body) > $this->maxBytes) {
            return Receipt::TooLarge;
        }

        if ($this->throttle instanceof ReportThrottle && !$this->throttle->allow(hash_hmac('sha256', $clientAddress ?? '', $this->secret))) {
            return Receipt::TooManyRequests;
        }

        try {
            $reports = $this->parser->parse($contentType, $body);
        } catch (InvalidReport) {
            return Receipt::Malformed;
        }

        $client = null === $clientAddress ? null : $this->anonymizer?->anonymize($clientAddress);
        $now = $this->clock->now();
        $unique = [];

        foreach ($reports as $report) {
            $unique[$report->fingerprint()] ??= $report;
        }

        foreach ($unique as $fingerprint => $report) {
            $this->repository?->record($report, $client, $now);

            if ($this->dispatcher instanceof EventDispatcherInterface && ($this->deduplicator?->isNew($fingerprint) ?? true)) {
                $this->dispatcher->dispatch(new ViolationReported($report, $client));
            }
        }

        return Receipt::Accepted;
    }
}
