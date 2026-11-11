<?php

declare(strict_types=1);

namespace Phil\UploadScan;

use Phil\UploadScan\Exception\InvalidArgument;

/**
 * The answer of a scanner about one file: clean; infected, with the name of the signature that matched; or an error,
 * with the reason the file was not scanned to its end. The signature and the reason are 1 to 255 bytes of printable
 * ASCII, so that they can be logged and shown to an operator as they are.
 */
final readonly class ScanResult
{
    /** The longest signature or reason a result keeps, in bytes. */
    public const int MAX_TEXT_BYTES = 255;

    private function __construct(
        public ScanStatus $status,
        public ?string $signature = null,
        public ?string $reason = null,
    ) {
    }

    public static function clean(): self
    {
        return new self(ScanStatus::Clean);
    }

    /**
     * @throws InvalidArgument when the signature is empty, longer than 255 bytes or not printable ASCII
     */
    public static function infected(string $signature): self
    {
        return new self(ScanStatus::Infected, signature: self::text($signature, 'signature'));
    }

    /**
     * @throws InvalidArgument when the reason is empty, longer than 255 bytes or not printable ASCII
     */
    public static function error(string $reason): self
    {
        return new self(ScanStatus::Error, reason: self::text($reason, 'reason'));
    }

    public function isClean(): bool
    {
        return ScanStatus::Clean === $this->status;
    }

    public function isInfected(): bool
    {
        return ScanStatus::Infected === $this->status;
    }

    public function isError(): bool
    {
        return ScanStatus::Error === $this->status;
    }

    private static function text(string $text, string $what): string
    {
        if (1 !== preg_match('/^[\x21-\x7E](?:[\x20-\x7E]{0,253}[\x21-\x7E])?$/D', $text)) {
            throw InvalidArgument::because(\sprintf('A scan %s is 1 to %d bytes of printable ASCII, without spaces around it.', $what, self::MAX_TEXT_BYTES));
        }

        return $text;
    }
}
