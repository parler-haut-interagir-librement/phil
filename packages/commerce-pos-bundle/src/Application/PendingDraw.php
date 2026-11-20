<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use Phil\CommerceBundle\Domain\ValueObject\SellableReference;

/**
 * An option of a paid order's line still to draw, which the register enters by a form: the line, the option, its
 * drawer, how many units — one draw each — and the form's template.
 */
final readonly class PendingDraw
{
    public function __construct(
        public SellableReference $reference,
        public string $description,
        public string $option,
        public ?string $label,
        public string $drawer,
        public int $units,
        public string $template,
    ) {
    }
}
