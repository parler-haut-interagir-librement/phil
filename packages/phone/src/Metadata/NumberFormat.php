<?php

declare(strict_types=1);

namespace Phil\Phone\Metadata;

/**
 * How a territory groups the numbers a pattern matches, nationally and internationally.
 */
final readonly class NumberFormat
{
    /**
     * @param ?string $leadingDigits the digits the numbers start with, the most precise of the metadata's
     * @param ?string $nationalRule  the first group as written nationally, `0 $1`, `($1)`
     * @param ?string $intlFormat    `null` when the numbers are not written internationally
     */
    public function __construct(
        public string $pattern,
        public ?string $leadingDigits,
        public string $format,
        public ?string $nationalRule,
        public ?string $intlFormat,
    ) {
    }

    public function applies(string $nationalNumber): bool
    {
        return (null === $this->leadingDigits || 1 === preg_match('/^(?:'.$this->leadingDigits.')/', $nationalNumber))
            && 1 === preg_match('/^(?:'.$this->pattern.')$/', $nationalNumber);
    }

    public function apply(string $nationalNumber, string $format): string
    {
        return preg_replace('/^(?:'.$this->pattern.')$/', $format, $nationalNumber) ?? $nationalNumber;
    }
}
