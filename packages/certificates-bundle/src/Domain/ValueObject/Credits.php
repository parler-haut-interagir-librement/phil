<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\ValueObject;

use Phil\CertificatesBundle\Domain\Exception\InvalidValue;

/**
 * Credits of continuing education — hours, or credits, as the application counts them —, to the hundredth: `1.5` is
 * kept as 150 hundredths, so that sums stay exact. From 0 to 9 999 999.99.
 */
final readonly class Credits
{
    public const int MAX = 999_999_999;

    private function __construct(
        public int $hundredths,
    ) {
    }

    public static function fromHundredths(int $hundredths): self
    {
        if ($hundredths < 0 || $hundredths > self::MAX) {
            throw InvalidValue::because(\sprintf('Credits are from 0 to 9999999.99; %d hundredths are not.', $hundredths));
        }

        return new self($hundredths);
    }

    /**
     * Credits as they are typed: digits, with up to two decimals after a point or a comma — `3`, `1.5`, `0,25`.
     */
    public static function fromString(string $credits): self
    {
        if (1 !== preg_match('/^(\d{1,7})(?:[.,](\d{1,2}))?$/D', $credits, $parts)) {
            throw InvalidValue::because(\sprintf('Credits are a number with up to two decimals, such as 1.5; "%s" is not.', $credits));
        }

        return new self((int) ($parts[1].str_pad($parts[2] ?? '', 2, '0')));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(self $other): self
    {
        return self::fromHundredths($this->hundredths + $other->hundredths);
    }

    public function covers(self $required): bool
    {
        return $this->hundredths >= $required->hundredths;
    }

    /**
     * What is still missing to reach the credits required, zero when they are reached.
     */
    public function missingTo(self $required): self
    {
        return new self(max(0, $required->hundredths - $this->hundredths));
    }

    /**
     * The credits written back, without trailing zeros: `3`, `1.5`, `0.25`.
     */
    public function formatted(): string
    {
        $units = intdiv($this->hundredths, 100);
        $rest = $this->hundredths % 100;

        return 0 === $rest ? (string) $units : rtrim(\sprintf('%d.%02d', $units, $rest), '0');
    }
}
