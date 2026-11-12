<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application;

use JsonException;
use Phil\BrowserSecurityBundle\Application\Exception\InvalidReport;
use Phil\BrowserSecurityBundle\Domain\Enum\Disposition;
use Phil\BrowserSecurityBundle\Domain\ValueObject\ViolationReport;

/**
 * Reads the reports a browser posts, in either format:
 *
 * - `application/csp-report`, one `{"csp-report": {...}}` object with hyphenated keys, sent for `report-uri`;
 * - `application/reports+json`, a list of reports of the Reporting API, sent for `report-to`: those of type
 *   `csp-violation`, and those of the cross-origin and integrity policies under the name of their header; the others
 *   are ignored.
 *
 * `application/json` is read as whichever shape its body has. The JSON is read eight levels deep at most, a body gives
 * a bounded number of reports, and each field is cut to its bound: an address loses its credentials, its query and
 * its fragment, where tokens and personal data hide; a sample keeps 40 characters. A report without a page or a
 * directive is dropped.
 */
final readonly class ReportParser
{
    public const string CSP_REPORT = 'application/csp-report';

    public const string REPORTS_JSON = 'application/reports+json';

    public const string JSON = 'application/json';

    public const int DEFAULT_MAX_REPORTS = 20;

    public const int MAX_URI_LENGTH = 2048;

    public const int MAX_SAMPLE_LENGTH = 40;

    private const int MAX_DEPTH = 8;

    private const int MAX_NUMBER = 1_000_000_000;

    private const array OTHER_REPORTS = ['coop' => 'cross-origin-opener-policy', 'coep' => 'cross-origin-embedder-policy', 'integrity-violation' => 'integrity-policy'];

    private const array LEGACY_KEYS = [
        'document' => 'document-uri', 'blocked' => 'blocked-uri', 'effective' => 'effective-directive',
        'violated' => 'violated-directive', 'disposition' => 'disposition', 'file' => 'source-file',
        'line' => 'line-number', 'column' => 'column-number', 'status' => 'status-code', 'sample' => 'script-sample',
    ];

    private const array REPORTING_KEYS = [
        'document' => 'documentURL', 'blocked' => 'blockedURL', 'effective' => 'effectiveDirective',
        'violated' => 'violatedDirective', 'disposition' => 'disposition', 'file' => 'sourceFile',
        'line' => 'lineNumber', 'column' => 'columnNumber', 'status' => 'statusCode', 'sample' => 'sample',
    ];

    public function __construct(
        private int $maxReports = self::DEFAULT_MAX_REPORTS,
    ) {
    }

    /**
     * The media type of a `Content-Type` header, without its parameters, in lower case.
     */
    public static function mediaType(string $contentType): string
    {
        return strtolower(trim(explode(';', $contentType)[0]));
    }

    public function supports(string $contentType): bool
    {
        return \in_array(self::mediaType($contentType), [self::CSP_REPORT, self::REPORTS_JSON, self::JSON], true);
    }

    /**
     * @return list<ViolationReport>
     *
     * @throws InvalidReport when the body is not JSON, or not in the shape its media type announces
     */
    public function parse(string $contentType, string $body): array
    {
        try {
            $data = json_decode($body, true, self::MAX_DEPTH, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new InvalidReport('The report body is not JSON within eight levels.', $jsonException->getCode(), previous: $jsonException);
        }

        $type = self::mediaType($contentType);

        if (!\is_array($data) || (self::REPORTS_JSON === $type && !array_is_list($data)) || (self::CSP_REPORT === $type && array_is_list($data))) {
            throw new InvalidReport(\sprintf('The report body is not in the shape of %s.', $type));
        }

        $bodies = array_is_list($data) ? $this->violations($data) : [$data['csp-report'] ?? null];
        $keys = array_is_list($data) ? self::REPORTING_KEYS : self::LEGACY_KEYS;
        $reports = [];

        foreach ($bodies as $body) {
            $report = \is_array($body) ? $this->report($body, $keys) : null;

            if (!$report instanceof ViolationReport) {
                continue;
            }

            $reports[] = $report;

            if (\count($reports) === $this->maxReports) {
                return $reports;
            }
        }

        return $reports;
    }

    /**
     * The bodies of the reports read in a list of the Reporting API, those of the other policies in the shape of a
     * violation.
     *
     * @param list<mixed> $items
     *
     * @return list<mixed>
     */
    private function violations(array $items): array
    {
        $bodies = [];

        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $type = $item['type'] ?? null;
            $body = $item['body'] ?? null;

            if ('csp-violation' === $type) {
                $bodies[] = $body;
            } elseif (\is_string($type) && isset(self::OTHER_REPORTS[$type]) && \is_array($body)) {
                $bodies[] = [
                    'documentURL' => $body['documentURL'] ?? $item['url'] ?? null,
                    'blockedURL' => $body['blockedURL'] ?? null,
                    'effectiveDirective' => self::OTHER_REPORTS[$type],
                    'disposition' => 'enforce' === ($body['disposition'] ?? null) || false === ($body['reportOnly'] ?? null) ? 'enforce' : 'report',
                ];
            }
        }

        return $bodies;
    }

    /**
     * @param array<array-key, mixed> $body
     * @param array<string, string>   $keys
     */
    private function report(array $body, array $keys): ?ViolationReport
    {
        $document = $this->uri($body[$keys['document']] ?? null);
        $directive = $this->directive($body[$keys['effective']] ?? $body[$keys['violated']] ?? null);

        if (null === $document || null === $directive) {
            return null;
        }

        return new ViolationReport(
            $document,
            $this->uri($body[$keys['blocked']] ?? null) ?? '',
            $directive,
            \is_string($body[$keys['disposition']] ?? null) ? Disposition::tryFrom($body[$keys['disposition']]) ?? Disposition::Enforce : Disposition::Enforce,
            $this->uri($body[$keys['file']] ?? null),
            $this->number($body[$keys['line']] ?? null),
            $this->number($body[$keys['column']] ?? null),
            $this->number($body[$keys['status']] ?? null),
            $this->sample($body[$keys['sample']] ?? null),
        );
    }

    private function uri(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = explode('?', explode('#', $this->printable($value))[0])[0];
        $value = (string) preg_replace('~^([A-Za-z][A-Za-z0-9+.-]*://)[^/@]*@~', '$1', $value);
        $value = mb_strcut($value, 0, self::MAX_URI_LENGTH, 'UTF-8');

        return '' === $value ? null : $value;
    }

    private function directive(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $directive = explode(' ', trim($value))[0];

        return 1 === preg_match('/^[a-z][a-z-]{0,63}$/D', $directive) ? $directive : null;
    }

    private function number(mixed $value): ?int
    {
        $number = \is_int($value) ? $value : (\is_string($value) && ctype_digit($value) ? (int) $value : null);

        return null !== $number && $number >= 0 && $number <= self::MAX_NUMBER ? $number : null;
    }

    private function sample(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $sample = mb_substr($this->printable($value), 0, self::MAX_SAMPLE_LENGTH, 'UTF-8');

        return '' === $sample ? null : $sample;
    }

    /**
     * The text without its control characters: JSON gives valid UTF-8 only.
     */
    private function printable(string $value): string
    {
        return (string) preg_replace('/[\x00-\x1F\x7F]/', '', $value);
    }
}
