<?php

declare(strict_types=1);

namespace Phil\BrailleExportLiblouis\Exception;

use Throwable;

/**
 * A translation liblouis could not do: the program stopped, ran out of time, or gave an answer the package cannot read;
 * a batch or an answer over its limit.
 */
final class LiblouisFailed extends LiblouisException
{
    public static function exited(int $exitCode, string $errorOutput): self
    {
        return new self(\sprintf('liblouis stopped with exit code %d.', $exitCode), $exitCode, $errorOutput);
    }

    public static function timedOut(string $binary, string $errorOutput, Throwable $previous): self
    {
        return new self(\sprintf('"%s" ran out of time.', $binary), null, $errorOutput, $previous);
    }

    public static function notRun(string $binary, Throwable $previous): self
    {
        return new self(\sprintf('"%s" did not run to its end: %s', $binary, $previous->getMessage()), null, '', $previous);
    }

    public static function inputTooLarge(int $limit): self
    {
        return new self(\sprintf('The texts are over the limit of %d bytes.', $limit));
    }

    public static function outputTooLarge(int $limit): self
    {
        return new self(\sprintf('liblouis wrote more than the limit of %d bytes.', $limit));
    }

    public static function lineCount(int $expected, int $actual): self
    {
        return new self(\sprintf('liblouis gave %d lines back for %d.', $actual, $expected), 0);
    }

    public static function notBraille(int $line): self
    {
        return new self(\sprintf('liblouis gave line %d back with characters that are not braille cells: is the display table unicode.dis?', $line), 0);
    }

    public static function noVersion(string $output): self
    {
        return new self('liblouis did not say which version it is.', 0, $output);
    }
}
