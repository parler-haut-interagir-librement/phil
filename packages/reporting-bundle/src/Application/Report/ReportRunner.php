<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Report;

use Phil\ReportingBundle\Application\Contract\ExportStore;
use Phil\ReportingBundle\Application\Contract\JobStore;
use Phil\ReportingBundle\Application\Contract\ReportDelivery;
use Phil\ReportingBundle\Application\Event\ReportExported;
use Phil\ReportingBundle\Application\Exception\NotFound;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;
use Throwable;

/**
 * A kept job run to its end: the report exported to the export store, delivered to the job's recipients when it names
 * a delivery, the delivered file and the job forgotten, and `ReportExported` dispatched.
 */
final readonly class ReportRunner
{
    /**
     * @param ServiceProviderInterface<ReportDelivery> $deliveries
     */
    public function __construct(
        private Reports $reports,
        private JobStore $jobs,
        private ExportStore $exports,
        private ServiceProviderInterface $deliveries,
        private ?EventDispatcherInterface $dispatcher = null,
        private bool $keepDelivered = false,
    ) {
    }

    /**
     * @throws NotFound when the job names a report, a format or a delivery that does not exist
     */
    public function run(string $id): StoredExport
    {
        $job = $this->jobs->find($id);
        $delivery = $this->delivery($job->delivery);
        $stream = ReportStream::temporary();

        try {
            $rows = $this->reports->write($job->report, $job->format, $job->request, $stream);
            $stream->rewind();
            $export = $this->exports->keep(
                $stream,
                $this->reports->filename($job->report, $job->format, $job->request),
                $this->reports->format($job->format)->mediaType(),
                $rows,
            );
        } finally {
            $stream->close();
        }

        if ($delivery instanceof ReportDelivery) {
            $this->deliver($delivery, $job, $export);
        }

        $this->jobs->remove($id);
        $this->dispatcher?->dispatch(new ReportExported($id, $export->id, $delivery instanceof ReportDelivery));

        return $export;
    }

    private function delivery(?string $key): ?ReportDelivery
    {
        if (null === $key) {
            return null;
        }

        return $this->deliveries->has($key) ? $this->deliveries->get($key) : throw NotFound::of('report delivery', $key);
    }

    private function deliver(ReportDelivery $delivery, ReportJob $job, StoredExport $export): void
    {
        try {
            $content = $this->exports->open($export->id);

            try {
                $delivery->deliver($job, $export, $content);
            } finally {
                $content->close();
            }
        } catch (Throwable $throwable) {
            // The job stays for a retry, which exports again: the file of this attempt is not left behind.
            $this->exports->remove($export->id);

            throw $throwable;
        }

        if (!$this->keepDelivered) {
            $this->exports->remove($export->id);
        }
    }
}
