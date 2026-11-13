<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Infrastructure\Messenger;

use Phil\Epub\Exception\EpubException;
use Phil\Epub\Exception\WriteFailed;
use Phil\EpubBundle\Application\Epubs;
use Phil\EpubBundle\Application\Event\EpubStored;
use Phil\EpubBundle\Application\Exception\StorageFailed;
use Phil\EpubBundle\Application\Message\WriteEpub;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Handles `WriteEpub`: builds the book, writes it, keeps it, then dispatches `EpubStored`. A failure that may pass — a
 * temporary file or a storage that cannot be written — is retried by Messenger; a book that can never be written — an
 * unknown source, a book the library refuses, a font that cannot be embedded — is not.
 */
final readonly class EpubHandler
{
    public function __construct(
        private Epubs $epubs,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    public function __invoke(WriteEpub $message): void
    {
        try {
            $location = $this->epubs->store($this->epubs->build($message->source, $message->id), $message->path);
        } catch (EpubException $epubException) {
            throw $epubException instanceof StorageFailed || $epubException instanceof WriteFailed ? new RecoverableMessageHandlingException($epubException->getMessage(), previous: $epubException) : new UnrecoverableMessageHandlingException($epubException->getMessage(), previous: $epubException);
        }

        $this->events?->dispatch(new EpubStored($message->source, $message->id, $message->path, $location));
    }
}
