<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application;

use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\LibreOffice\LibreOfficeOptions;
use Phil\PagedMedia\Gotenberg\Pipeline;
use Phil\PagedMediaBundle\Application\Event\PrintStarting;
use Phil\PagedMediaBundle\Application\Event\UnnamedPrintStarting;
use Phil\PagedMediaBundle\Application\Job\ConversionJob;
use Phil\PagedMediaBundle\Application\Job\PipelineJob;
use Phil\PagedMediaBundle\Application\Job\RenderJob;
use Phil\PagedMediaBundle\Application\Message\ConvertDocuments;
use Phil\PagedMediaBundle\Application\Message\RenderPdf;
use Phil\PagedMediaBundle\Application\Message\RunPipeline;
use Phil\PagedMediaBundle\Application\Port\JobStore;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * What `PagedMedia` does, done in a worker: each call keeps a job in the job store and dispatches a message that
 * carries only its identifier, so no document, parameter, option or key travels in the queue. Each call returns that
 * identifier, which the `PdfStored` event gives back once the file is kept. A print announces itself before it is
 * queued, by `PrintStarting` when it names its work and `UnnamedPrintStarting` otherwise: a listener that throws stops
 * it in the request that asked for it.
 */
final readonly class PagedMediaQueue
{
    public function __construct(
        private MessageBusInterface $bus,
        private JobStore $jobs,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * Renders a template, runs the stages when there are any — then signs and timestamps the result with the
     * configuration's certificate and authority when asked —, and keeps the file under the path.
     *
     * @param array<string, mixed> $parameters scalars and arrays
     * @param string               $path       where the storage keeps the file, relative to it
     */
    public function render(string $template, array $parameters, string $path, ?Pipeline $pipeline = null, bool $sign = false, bool $timestamp = false, string $filename = 'document.pdf', ?PrintSubject $subject = null): string
    {
        $this->announce($subject);
        $job = $this->jobs->keep(new RenderJob($template, $parameters, $path, $pipeline, $sign, $timestamp, $filename));
        $this->bus->dispatch(new RenderPdf($job));

        return $job;
    }

    /**
     * Runs a list of stages on PDFs, then signs and timestamps the result when asked, and keeps the file under the path.
     */
    public function run(Files $files, Pipeline $pipeline, string $path, bool $sign = false, bool $timestamp = false, ?PrintSubject $subject = null): string
    {
        $this->announce($subject);
        $job = $this->jobs->keep(new PipelineJob($files, $pipeline, $path, $sign, $timestamp));
        $this->bus->dispatch(new RunPipeline($job));

        return $job;
    }

    /**
     * Converts office documents with LibreOffice, runs the stages when there are any, then signs and timestamps the
     * result when asked, and keeps the file under the path.
     */
    public function convert(Files $documents, string $path, LibreOfficeOptions $options = new LibreOfficeOptions(), ?Pipeline $pipeline = null, bool $sign = false, bool $timestamp = false, ?PrintSubject $subject = null): string
    {
        $this->announce($subject);
        $job = $this->jobs->keep(new ConversionJob($documents, $path, $options, $pipeline, $sign, $timestamp));
        $this->bus->dispatch(new ConvertDocuments($job));

        return $job;
    }

    private function announce(?PrintSubject $subject): void
    {
        $this->events?->dispatch($subject instanceof PrintSubject ? new PrintStarting($subject) : new UnnamedPrintStarting());
    }
}
