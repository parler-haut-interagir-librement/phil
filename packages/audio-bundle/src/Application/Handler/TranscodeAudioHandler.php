<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Application\Handler;

use Phil\Audio\Contract\Transcoder;
use Phil\Audio\Exception\AudioException;
use Phil\Audio\Exception\AudioToolUnavailable;
use Phil\Audio\Excerpt;
use Phil\AudioBundle\Application\Contract\AudioFiles;
use Phil\AudioBundle\Application\Contract\RenditionStore;
use Phil\AudioBundle\Application\Exception\AudioNotFound;
use Phil\AudioBundle\Application\Exception\UnknownRendition;
use Phil\AudioBundle\Application\Message\TranscodeAudio;
use Phil\AudioBundle\Application\Renditions;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Produces a rendition: a local copy of the file, transcoded to a temporary file, kept by the store, both temporary
 * files deleted whatever happens. A file that is gone, a rendition no longer configured or a file the tool refuses is
 * not retried; a tool that cannot be started fails as any error, under the retry strategy of the transport.
 */
final readonly class TranscodeAudioHandler
{
    public function __construct(
        private AudioFiles $files,
        private RenditionStore $store,
        private Transcoder $transcoder,
        private Renditions $renditions,
        private ?string $temporaryDirectory = null,
    ) {
    }

    public function __invoke(TranscodeAudio $message): void
    {
        try {
            $rendition = $this->renditions->get($message->rendition);
            $excerpt = null === $message->excerptStart || null === $message->excerptDuration ? null : new Excerpt($message->excerptStart, $message->excerptDuration);
            $source = $this->files->localCopy($message->mediaId);
        } catch (AudioNotFound|UnknownRendition|AudioException $exception) {
            throw new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception);
        }

        $target = rtrim($this->temporaryDirectory ?? sys_get_temp_dir(), '/').'/phil-audio-'.sodium_bin2hex(random_bytes(8)).'.'.$rendition->format->extension();

        try {
            $this->transcoder->transcode($source, $target, $rendition, $excerpt);
            $this->store->save($message->mediaId, $message->rendition, $rendition->format, $target, $excerpt instanceof Excerpt);
        } catch (AudioToolUnavailable $exception) {
            throw $exception;
        } catch (AudioException $exception) {
            throw new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception);
        } finally {
            $this->files->release($source);

            if (is_file($target)) {
                unlink($target);
            }
        }
    }
}
