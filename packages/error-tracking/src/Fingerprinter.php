<?php

declare(strict_types=1);

namespace Phil\ErrorTracking;

/**
 * The fingerprint that groups events: a SHA-256 of each exception of the chain — its type, its message without its
 * variable values, and the file and function of its first frames of the application, without their lines, so that an
 * edit elsewhere in a file does not split a group. Frames of the dependencies are left out: an upgrade of a library
 * keeps the groups of the application.
 */
final readonly class Fingerprinter
{
    public const int MAX_FRAMES = 10;

    public function __construct(
        private MessageNormalizer $normalizer = new MessageNormalizer(),
    ) {
    }

    /**
     * @return non-empty-string 64 hexadecimal digits
     */
    public function fingerprint(ErrorEvent $event): string
    {
        $parts = [];

        foreach ($event->exceptions as $exception) {
            $parts[] = $exception->type;
            $parts[] = $this->normalizer->normalize($exception->message);
            $frames = 0;

            foreach ($exception->frames as $frame) {
                if ($frame->inApp && $frames++ < self::MAX_FRAMES) {
                    $parts[] = $frame->file.'|'.$frame->function;
                }
            }

            $parts[] = '';
        }

        return hash('sha256', implode("\n", $parts));
    }
}
