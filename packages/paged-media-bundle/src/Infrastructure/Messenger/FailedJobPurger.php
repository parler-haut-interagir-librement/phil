<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Infrastructure\Messenger;

use Phil\PagedMediaBundle\Application\Exception\StorageFailed;
use Phil\PagedMediaBundle\Application\Message\ConvertDocuments;
use Phil\PagedMediaBundle\Application\Message\RenderPdf;
use Phil\PagedMediaBundle\Application\Message\RunPipeline;
use Phil\PagedMediaBundle\Application\Port\JobStore;
use Symfony\Component\Messenger\Event\SyncMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * Forgets the job of a message that failed for good — a failure that is not retried, or the last retry spent —, so
 * that the documents and parameters it holds do not stay in the job store. A message that will be retried keeps its
 * job. It listens after Messenger has decided on the retry, in a worker and on the `sync://` transport alike.
 *
 * A job the store cannot remove is left where it is: the worker goes on with the next message.
 */
final readonly class FailedJobPurger
{
    public function __construct(
        private JobStore $jobs,
    ) {
    }

    public function onWorkerMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if (!$event->willRetry()) {
            $this->purge($event->getEnvelope()->getMessage());
        }
    }

    public function onSyncMessageFailed(SyncMessageFailedEvent $event): void
    {
        if (!$event->willRetry()) {
            $this->purge($event->getEnvelope()->getMessage());
        }
    }

    private function purge(object $message): void
    {
        if (!$message instanceof RenderPdf && !$message instanceof RunPipeline && !$message instanceof ConvertDocuments) {
            return;
        }

        try {
            $this->jobs->remove($message->job);
        } catch (StorageFailed) {
            // Left for the operator: the failure that brought the message here is the one to report.
        }
    }
}
