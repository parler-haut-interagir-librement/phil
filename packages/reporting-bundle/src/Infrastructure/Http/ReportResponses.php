<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Http;

use Phil\ReportingBundle\Application\Exception\NotFound;
use Phil\ReportingBundle\Application\Report\ReportRequest;
use Phil\ReportingBundle\Application\Report\Reports;
use Phil\ReportingBundle\Application\Report\ReportStream;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A report as a download from a controller of the application, which checks first who may read it: the rows streamed
 * to the client as they are read, never held whole in memory.
 */
final readonly class ReportResponses
{
    public function __construct(
        private Reports $reports,
    ) {
    }

    /**
     * @throws NotFound when the report or the format is unknown, before anything is sent
     */
    public function download(string $report, string $format, ReportRequest $request): StreamedResponse
    {
        $this->reports->definition($report);
        $writer = $this->reports->format($format);
        $filename = $this->reports->filename($report, $format, $request);

        return new StreamedResponse(function () use ($report, $format, $request): void {
            $output = fopen('php://output', 'w');

            if (false === $output) {
                throw new RuntimeException('The output stream cannot be opened.');
            }

            $this->reports->write($report, $format, $request, ReportStream::of($output));
        }, Response::HTTP_OK, [
            'Content-Type' => $writer->mediaType(),
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
