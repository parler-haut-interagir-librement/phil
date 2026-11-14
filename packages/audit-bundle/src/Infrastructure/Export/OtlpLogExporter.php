<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Infrastructure\Export;

use Override;
use Phil\AuditBundle\Application\Exception\ExportFailed;
use Phil\AuditBundle\Domain\Contract\FactExporter;
use Phil\AuditBundle\Domain\Export\ExportedFact;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Facts sent as OpenTelemetry log records to a collector, by OTLP over HTTP in its JSON encoding: one request per
 * batch to the collector's logs endpoint, such as `https://collector.internal:4318/v1/logs`. Each record's body is
 * the action — the kind of row when it has none —, its time the row's instant, and its attributes the facts, under
 * `phil.audit.*`; the resource names the service. A status other than 2xx, or no answer, fails the batch.
 *
 * The endpoint is read by `phil/url`, as a browser reads it: `http` or `https`, a host name of two labels at least, or
 * `localhost` for a collector beside the application; an address with credentials, an IP address or a single name is
 * refused as the exporter is built. Redirects are never followed: a collector that answers `3xx` fails the batch,
 * so that the facts and the headers — an authorization among them — never reach another host.
 */
final readonly class OtlpLogExporter implements FactExporter
{
    /** The scope the records are emitted by. */
    public const string SCOPE = 'phil/audit-bundle';

    /** OpenTelemetry's severity number of INFO. */
    private const int INFO = 9;

    /** The collector's logs endpoint, as `phil/url` writes it. */
    private string $endpoint;

    /**
     * @param array<string, string> $headers sent with every request, such as an authorization the collector asks for
     *
     * @throws InvalidUrl when the endpoint is not an address `phil/url` accepts
     */
    public function __construct(
        private HttpClientInterface $client,
        string $endpoint,
        private string $serviceName,
        private array $headers = [],
        private float $timeout = 10.0,
    ) {
        $this->endpoint = self::endpoint($endpoint)->toString();
    }

    /**
     * The endpoint as the exporter reads it, `localhost` allowed.
     *
     * @throws InvalidUrl when the endpoint is not an address `phil/url` accepts
     */
    public static function endpoint(string $endpoint): Url
    {
        return Url::fromString($endpoint, allowLocalhost: true);
    }

    #[Override]
    public function export(array $facts): void
    {
        try {
            $response = $this->client->request('POST', $this->endpoint, [
                'headers' => ['Content-Type' => 'application/json'] + $this->headers,
                'body' => json_encode($this->body($facts), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
                'timeout' => $this->timeout,
                'max_redirects' => 0,
            ]);
            $status = $response->getStatusCode();
        } catch (ExceptionInterface $exception) {
            throw ExportFailed::because(\sprintf('%d fact(s) could not be sent to the collector: %s', \count($facts), $exception->getMessage()), $exception);
        }

        if ($status < 200 || $status > 299) {
            throw ExportFailed::because(\sprintf('The collector answered %d to %d fact(s).', $status, \count($facts)));
        }
    }

    /**
     * The request body: one resource, one scope, a record per fact.
     *
     * @param list<ExportedFact> $facts
     *
     * @return array{resourceLogs: list<array<string, mixed>>}
     */
    public function body(array $facts): array
    {
        return ['resourceLogs' => [[
            'resource' => ['attributes' => [$this->attribute('service.name', $this->serviceName)]],
            'scopeLogs' => [[
                'scope' => ['name' => self::SCOPE],
                'logRecords' => array_map($this->record(...), $facts),
            ]],
        ]]];
    }

    /**
     * @return array<string, mixed>
     */
    private function record(ExportedFact $fact): array
    {
        $attributes = [
            $this->attribute('phil.audit.segment', $fact->segment),
            ['key' => 'phil.audit.sequence', 'value' => ['intValue' => (string) $fact->sequence]],
            $this->attribute('phil.audit.kind', $fact->kind),
        ];

        foreach (['action' => $fact->action, 'actor' => $fact->actor, 'subject_type' => $fact->subjectType, 'subject_id' => $fact->subjectId, 'tenant' => $fact->tenant] as $key => $value) {
            if (null !== $value) {
                $attributes[] = $this->attribute('phil.audit.'.$key, $value);
            }
        }

        $attributes[] = $this->attribute('phil.audit.hash', $fact->hash);

        return [
            'timeUnixNano' => $fact->unixNanos,
            'severityNumber' => self::INFO,
            'severityText' => 'INFO',
            'body' => ['stringValue' => $fact->action ?? $fact->kind],
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{key: string, value: array{stringValue: string}}
     */
    private function attribute(string $key, string $value): array
    {
        return ['key' => $key, 'value' => ['stringValue' => $value]];
    }
}
