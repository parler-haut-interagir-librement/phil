<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Domain\Enum;

/**
 * Who a visit comes from, as far as its request tells: a person, or one of the machines that read a link before or
 * instead of a person. Only a person's visit counts towards a link's visits and its maximum.
 */
enum VisitorClass: string
{
    /** A person's browser following the link. */
    case Human = 'human';

    /** A crawler, a script, a monitor, a client that names no browser. */
    case Robot = 'robot';

    /** A browser fetching the link ahead of a click that may never come: `Sec-Purpose: prefetch`. */
    case Prefetch = 'prefetch';

    /** A messaging service or a social network reading the link to draw its preview. */
    case LinkPreview = 'link_preview';

    public function isHuman(): bool
    {
        return self::Human === $this;
    }
}
