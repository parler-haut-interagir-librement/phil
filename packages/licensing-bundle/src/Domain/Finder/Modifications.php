<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\Finder;

/**
 * What others may do with the work once they change it.
 */
enum Modifications: string
{
    /** Adaptations are shared under any terms. */
    case Allowed = 'allowed';

    /** Adaptations are shared under the work's own license. */
    case ShareAlike = 'share_alike';

    /** Adaptations are not shared. */
    case Forbidden = 'forbidden';
}
