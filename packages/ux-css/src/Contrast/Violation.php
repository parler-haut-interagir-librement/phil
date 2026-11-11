<?php

declare(strict_types=1);

namespace Phil\UxCss\Contrast;

use Phil\UxCss\Token\Scheme;
use Stringable;

/**
 * A pair of colours that breaks the contrast rule, or a property the rule needs and cannot resolve.
 */
final readonly class Violation implements Stringable
{
    public function __construct(
        public string $foreground,
        public string $background,
        public ?Scheme $scheme,
        public ?float $ratio,
        public float $minimum,
        public string $reason,
    ) {
    }

    public function __toString(): string
    {
        return \sprintf(
            '%s on %s%s: %s',
            $this->foreground,
            $this->background,
            $this->scheme instanceof Scheme ? ' ('.$this->scheme->value.')' : '',
            $this->reason,
        );
    }
}
