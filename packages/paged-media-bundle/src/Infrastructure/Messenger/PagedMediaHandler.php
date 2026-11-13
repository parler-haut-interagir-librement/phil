<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Infrastructure\Messenger;

use Closure;
use Phil\PagedMedia\Exception\PagedMediaException;
use Phil\PagedMedia\Gotenberg\Exception\CertificateUnavailable;
use Phil\PagedMedia\Gotenberg\Exception\GotenbergFailure;
use Phil\PagedMediaBundle\Application\Event\PdfStored;
use Phil\PagedMediaBundle\Application\Exception\JobUnavailable;
use Phil\PagedMediaBundle\Application\Exception\StorageFailed;
use Phil\PagedMediaBundle\Application\Job\ConversionJob;
use Phil\PagedMediaBundle\Application\Job\PipelineJob;
use Phil\PagedMediaBundle\Application\Job\RenderJob;
use Phil\PagedMediaBundle\Application\Message\ConvertDocuments;
use Phil\PagedMediaBundle\Application\Message\RenderPdf;
use Phil\PagedMediaBundle\Application\Message\RunPipeline;
use Phil\PagedMediaBundle\Application\Output;
use Phil\PagedMediaBundle\Application\PagedMedia;
use Phil\PagedMediaBundle\Application\Port\JobStore;
use Phil\PagedMediaBundle\Application\Port\PdfStorage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Handles the bundle's messages: reads the job a message names from the job store, renders or runs it, keeps the file,
 * forgets the job, then dispatches `PdfStored`. A failure that may pass — Gotenberg out of reach or busy, a vault out
 * of reach, a storage that cannot write — is retried by Messenger, and the job waits for the retry; a request that can
 * never succeed, or a job the store cannot give, is not. The print was announced when it was queued: the handler
 * announces it no more.
 */
final readonly class PagedMediaHandler
{
    public function __construct(
        private PagedMedia $pagedMedia,
        private PdfStorage $storage,
        private JobStore $jobs,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    public function render(RenderPdf $message): void
    {
        $this->handle($message->job, RenderJob::class, fn (RenderJob $job): Output => $this->pagedMedia->alreadyAnnounced()->renderTemplate(
            $job->template,
            $job->parameters,
            $job->filename,
            $this->pagedMedia->finish($job->pipeline, $job->sign, $job->timestamp),
        ));
    }

    public function run(RunPipeline $message): void
    {
        $this->handle($message->job, PipelineJob::class, fn (PipelineJob $job): Output => $this->pagedMedia->alreadyAnnounced()->process(
            $job->files,
            $this->pagedMedia->finish($job->pipeline, $job->sign, $job->timestamp),
        ));
    }

    public function convert(ConvertDocuments $message): void
    {
        $this->handle($message->job, ConversionJob::class, fn (ConversionJob $job): Output => $this->pagedMedia->alreadyAnnounced()->convert(
            $job->documents,
            $job->options,
            $this->pagedMedia->finish($job->pipeline, $job->sign, $job->timestamp),
        ));
    }

    /**
     * @template T of RenderJob|PipelineJob|ConversionJob
     *
     * @param class-string<T>    $type
     * @param Closure(T): Output $produce
     */
    private function handle(string $id, string $type, Closure $produce): void
    {
        try {
            $job = $this->jobs->find($id);

            if (!$job instanceof $type) {
                throw JobUnavailable::because(\sprintf('The job kept under "%s" is not the kind its message handles.', $id));
            }

            $output = $produce($job);
            $this->storage->store($job->path, $output);
            $this->jobs->remove($id);
        } catch (PagedMediaException $pagedMediaException) {
            $retryable = ($pagedMediaException instanceof GotenbergFailure && $pagedMediaException->isRetryable()) || $pagedMediaException instanceof CertificateUnavailable || $pagedMediaException instanceof StorageFailed;

            throw $retryable ? new RecoverableMessageHandlingException($pagedMediaException->getMessage(), previous: $pagedMediaException) : new UnrecoverableMessageHandlingException($pagedMediaException->getMessage(), previous: $pagedMediaException);
        }

        $this->events?->dispatch(new PdfStored($id, $job->path, $output->filename, $output->contentType, $output->residual, $output->rolledBack));
    }
}
