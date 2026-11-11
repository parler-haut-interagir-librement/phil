<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Report;

use Phil\ReportingBundle\Application\Exception\InvalidReportJob;

/**
 * A report to export in a worker, as the job store keeps it: the report, the format, the request, and the delivery
 * with its recipients, or none to keep the file in the export store.
 */
final readonly class ReportJob
{
    public const int MAX_RECIPIENTS = 100;

    private const string KEY = '/^[A-Za-z0-9_.-]{1,64}$/D';

    /**
     * @param list<string> $recipients e-mail addresses, or what the delivery reads
     *
     * @throws InvalidReportJob when a key is out of shape, there are more than 100 recipients, or one is longer than 254
     *                          characters
     */
    public function __construct(
        public string $report,
        public string $format,
        public ReportRequest $request,
        public ?string $delivery = null,
        public array $recipients = [],
    ) {
        foreach (['report' => $report, 'format' => $format, 'delivery' => $delivery ?? 'none'] as $name => $key) {
            if (1 !== preg_match(self::KEY, $key)) {
                throw InvalidReportJob::because(\sprintf('the %s "%s" is not named by 1 to 64 letters, digits, dots, dashes or underscores', $name, $key));
            }
        }

        if (\count($recipients) > self::MAX_RECIPIENTS) {
            throw InvalidReportJob::because(\sprintf('more than %d recipients', self::MAX_RECIPIENTS));
        }

        foreach ($recipients as $recipient) {
            if ('' === $recipient || \strlen($recipient) > 254) {
                throw InvalidReportJob::because('a recipient is from 1 to 254 bytes');
            }
        }
    }

    /**
     * @return array{report: string, format: string, request: array<string, mixed>, delivery: string|null, recipients: list<string>}
     */
    public function toArray(): array
    {
        return [
            'report' => $this->report,
            'format' => $this->format,
            'request' => $this->request->toArray(),
            'delivery' => $this->delivery,
            'recipients' => $this->recipients,
        ];
    }

    /**
     * The job `toArray()` wrote, or null for anything else.
     */
    public static function fromArray(mixed $data): ?self
    {
        if (!\is_array($data) || !\is_string($data['report'] ?? null) || !\is_string($data['format'] ?? null)) {
            return null;
        }

        $request = ReportRequest::fromArray($data['request'] ?? null);
        $delivery = $data['delivery'] ?? null;
        $recipients = $data['recipients'] ?? null;

        if (!$request instanceof ReportRequest || (null !== $delivery && !\is_string($delivery)) || !\is_array($recipients)) {
            return null;
        }

        $addresses = [];

        foreach ($recipients as $recipient) {
            if (!\is_string($recipient)) {
                return null;
            }

            $addresses[] = $recipient;
        }

        try {
            return new self($data['report'], $data['format'], $request, $delivery, $addresses);
        } catch (InvalidReportJob) {
            return null;
        }
    }
}
