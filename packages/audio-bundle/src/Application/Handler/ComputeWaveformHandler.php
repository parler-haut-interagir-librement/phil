<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Application\Handler;

use Phil\Audio\Contract\PeaksGenerator;
use Phil\Audio\Exception\AudioException;
use Phil\Audio\Exception\AudioToolUnavailable;
use Phil\AudioBundle\Application\Contract\AudioFiles;
use Phil\AudioBundle\Application\Contract\RenditionStore;
use Phil\AudioBundle\Application\Exception\AudioNotFound;
use Phil\AudioBundle\Application\Message\ComputeWaveform;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Computes the waveform of a file from a local copy, released whatever happens, and keeps it in the store.
 */
final readonly class ComputeWaveformHandler
{
    public function __construct(
        private AudioFiles $files,
        private RenditionStore $store,
        private PeaksGenerator $generator,
        private int $samplesPerPixel,
    ) {
    }

    public function __invoke(ComputeWaveform $message): void
    {
        try {
            $source = $this->files->localCopy($message->mediaId);
        } catch (AudioNotFound $audioNotFound) {
            throw new UnrecoverableMessageHandlingException($audioNotFound->getMessage(), 0, $audioNotFound);
        }

        try {
            $this->store->savePeaks($message->mediaId, $this->generator->peaks($source, $this->samplesPerPixel));
        } catch (AudioToolUnavailable $exception) {
            throw $exception;
        } catch (AudioException $exception) {
            throw new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception);
        } finally {
            $this->files->release($source);
        }
    }
}
