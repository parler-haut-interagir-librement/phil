<?php

declare(strict_types=1);

namespace Phil\ErrorTracking;

use Phil\ErrorTracking\Internal\Text;

/**
 * One exception of a chain: its type, its message and its frames, the innermost first.
 */
final readonly class ExceptionRecord
{
    public const int MAX_TYPE_LENGTH = 255;

    public const int MAX_MESSAGE_LENGTH = 2048;

    public string $type;

    public string $message;

    /**
     * @param list<Frame> $frames
     */
    public function __construct(
        string $type,
        string $message,
        public array $frames = [],
    ) {
        $this->type = Text::symbol($type, self::MAX_TYPE_LENGTH);
        $this->message = Text::clip($message, self::MAX_MESSAGE_LENGTH);
    }

    /**
     * The innermost frame of the application, where the fault most likely lies.
     */
    public function culprit(): ?Frame
    {
        foreach ($this->frames as $frame) {
            if ($frame->inApp) {
                return $frame;
            }
        }

        return null;
    }
}
