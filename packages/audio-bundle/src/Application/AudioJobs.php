<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Application;

use Phil\Audio\Excerpt;
use Phil\AudioBundle\Application\Message\ComputeWaveform;
use Phil\AudioBundle\Application\Message\TranscodeAudio;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The jobs of an audio file just uploaded: each rendition of the configuration, the same renditions of the excerpt
 * when the creator offers one, and the waveform. Each job is a message of identifiers, for a worker to run. Without an
 * audio tool configured, there is no job: the files are served as they were uploaded.
 */
final readonly class AudioJobs
{
    public function __construct(
        private MessageBusInterface $bus,
        private Renditions $renditions,
        private bool $enabled = true,
    ) {
    }

    public function schedule(string $mediaId, ?Excerpt $excerpt = null): void
    {
        if (!$this->enabled) {
            return;
        }

        foreach ($this->renditions->names() as $name) {
            $this->bus->dispatch(new TranscodeAudio($mediaId, $name));

            if ($excerpt instanceof Excerpt) {
                $this->bus->dispatch(new TranscodeAudio($mediaId, $name, $excerpt->start, $excerpt->duration));
            }
        }

        $this->bus->dispatch(new ComputeWaveform($mediaId));
    }
}
