<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Application\Contract;

use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnSlip;

/**
 * Draws a return slip as a PDF, in the locale given. `Infrastructure\PagedMedia\PagedMediaReturnSlips` draws it with
 * the native writer of `phil/paged-media`.
 */
interface ReturnSlipRenderer
{
    /**
     * @return string the PDF's bytes
     */
    public function render(ReturnSlip $slip, ?string $locale = null): string;
}
