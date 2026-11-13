<?php

declare(strict_types=1);

namespace Phil\ErrorTracking;

use Phil\ErrorTracking\Internal\Text;

/**
 * One frame of a trace: the file, relative to the project when it lies in it, the line, the function called there,
 * and whether the frame belongs to the application rather than to a dependency. The arguments of the call are never
 * kept: they are where personal data hides.
 */
final readonly class Frame
{
    public const int MAX_FILE_LENGTH = 1024;

    public const int MAX_FUNCTION_LENGTH = 255;

    public string $file;

    public ?string $function;

    public function __construct(
        string $file,
        public int $line,
        ?string $function = null,
        public bool $inApp = false,
    ) {
        $this->file = Text::clip($file, self::MAX_FILE_LENGTH);
        $this->function = null === $function ? null : Text::symbol($function, self::MAX_FUNCTION_LENGTH);
    }

    /**
     * `App\Job::run (src/Job.php:12)`, or `src/Job.php:12` for code outside any function.
     */
    public function label(): string
    {
        $location = $this->file.':'.$this->line;

        return null === $this->function ? $location : $this->function.' ('.$location.')';
    }
}
