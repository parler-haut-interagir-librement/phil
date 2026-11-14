<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Contract;

use Phil\AuditBundle\Domain\Chain\Anchor;

/**
 * Reads back the anchors an `AnchorWriter` wrote, for verification.
 */
interface AnchorReader
{
    /**
     * @return list<Anchor> the anchors of the segment, oldest first
     */
    public function anchors(string $segment): array;
}
