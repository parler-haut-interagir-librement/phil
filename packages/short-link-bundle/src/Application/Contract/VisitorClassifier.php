<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application\Contract;

use Phil\ShortLinkBundle\Domain\Enum\VisitorClass;

/**
 * Tells who a visit comes from, from what its request says of itself. It answers from the headers alone, keeps none
 * of them, and holds no state between two calls.
 */
interface VisitorClassifier
{
    /**
     * @param string|null $userAgent the `User-Agent` header, none when the request sends none
     * @param string|null $purpose   the `Sec-Purpose`, `Purpose`, `X-Purpose` and `X-Moz` headers sent, joined by commas;
     *                               none when the request sends none of them
     */
    public function classify(?string $userAgent, ?string $purpose): VisitorClass;
}
