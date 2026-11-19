<?php

declare(strict_types=1);

namespace Phil\BrailleExportLiblouis\Exception;

use Phil\BrailleExport\Exception\TranslationFailed;
use RuntimeException;
use Throwable;

/**
 * What liblouis could not do: a missing program, a missing table, or another failure. The message names the failure and
 * never the text; `$errorOutput` may quote the text, and belongs in a log the application trusts.
 */
abstract class LiblouisException extends RuntimeException implements TranslationFailed
{
    /** The most of the program's error output kept. */
    private const int MAX_ERROR_OUTPUT = 65_536;

    public readonly string $errorOutput;

    /**
     * @param int|null $exitCode the code the program ended with, null when it did not end by itself or did not start
     */
    final public function __construct(
        string $message,
        public readonly ?int $exitCode = null,
        string $errorOutput = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);

        $this->errorOutput = substr($errorOutput, 0, self::MAX_ERROR_OUTPUT);
    }
}
