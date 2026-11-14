<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Application;

use Phil\UploadScanBundle\Application\Contract\RescanTarget;
use Phil\UploadScanBundle\Application\Event\FileRescanned;
use Phil\UploadScanBundle\Application\Exception\UnknownRescanTarget;
use Phil\UploadScanBundle\Application\Message\RescanFile;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Handles `RescanFile`: the file read from its target, inspected again under the context `rescan.<target>`, and the
 * inspection handed back to the target, then `FileRescanned` dispatched. A file that no longer exists is left alone.
 * The message carries identifiers only: the target reads the file where the application keeps it.
 */
final readonly class RescanFileHandler
{
    /**
     * @param iterable<RescanTarget> $targets
     */
    public function __construct(
        private UploadScanner $scanner,
        private iterable $targets,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    public function __invoke(RescanFile $message): void
    {
        $target = $this->target($message->target);
        $stream = $target->open($message->fileId);

        if (!\is_resource($stream)) {
            return;
        }

        try {
            $inspection = $this->scanner->inspectStream($stream, $message->fileId, 'rescan.'.$message->target);
        } finally {
            fclose($stream);
        }

        $target->resolve($message->fileId, $inspection);
        $this->events?->dispatch(new FileRescanned($message->target, $message->fileId, $inspection));
    }

    private function target(string $name): RescanTarget
    {
        foreach ($this->targets as $target) {
            if ($target->name() === $name) {
                return $target;
            }
        }

        throw UnknownRescanTarget::named($name);
    }
}
