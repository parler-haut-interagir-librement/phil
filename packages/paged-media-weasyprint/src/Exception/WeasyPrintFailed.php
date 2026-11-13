<?php

declare(strict_types=1);

namespace Phil\PagedMediaWeasyprint\Exception;

use RuntimeException;
use Throwable;

/**
 * A document WeasyPrint could not print. The message names the failure and never the document; `$errorOutput` may
 * quote its addresses, and belongs in a log the application trusts.
 */
final class WeasyPrintFailed extends RuntimeException
{
    /** The most of the command's error output kept, as a document can make it say a line per image. */
    private const int MAX_ERROR_OUTPUT = 65_536;

    public readonly string $errorOutput;

    /**
     * @param int|null $exitCode the code the command ended with, null when it did not end by itself
     */
    public function __construct(
        string $message,
        public readonly ?int $exitCode = null,
        string $errorOutput = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);

        $this->errorOutput = substr($errorOutput, 0, self::MAX_ERROR_OUTPUT);
    }

    public static function exited(int $exitCode, string $errorOutput): self
    {
        return new self(\sprintf('WeasyPrint stopped with exit code %d.', $exitCode), $exitCode, $errorOutput);
    }

    public static function timedOut(string $binary, string $errorOutput, Throwable $previous): self
    {
        return new self(\sprintf('"%s" ran out of time.', $binary), null, $errorOutput, $previous);
    }

    public static function notRun(string $binary, Throwable $previous): self
    {
        return new self(\sprintf('"%s" did not run to its end: %s', $binary, $previous->getMessage()), null, '', $previous);
    }

    public static function notPdf(string $errorOutput): self
    {
        return new self('WeasyPrint did not write a PDF.', 0, $errorOutput);
    }

    public static function noVersion(string $output): self
    {
        return new self('WeasyPrint did not say which version it is.', 0, $output);
    }

    public static function tooOld(string $version, string $minimum): self
    {
        return new self(\sprintf('WeasyPrint %s is older than the %s this package needs.', $version, $minimum));
    }

    public static function noAssetsDirectory(string $directory): self
    {
        return new self(\sprintf('The assets directory "%s" does not exist.', $directory));
    }

    public static function assetTooLarge(string $reference, int $limit): self
    {
        return new self(\sprintf('The asset "%s" is over the limit of %d bytes.', $reference, $limit));
    }

    public static function assetsOverBudget(): self
    {
        return new self('The assets of the document are over the total limit.');
    }
}
