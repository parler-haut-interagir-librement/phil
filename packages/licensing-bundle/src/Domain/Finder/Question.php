<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\Finder;

/**
 * A question of the license finder, in the order it asks them. Each is translated as `finder.<value>` in the domain
 * `phil_licensing`.
 */
enum Question: string
{
    /** Does the work adapt material under a share-alike license, which then imposes itself? */
    case AdaptedMaterial = 'adapted_material';

    /** Must whoever reuses the work credit its creators? */
    case Attribution = 'attribution';

    /** May the work be used for commercial purposes? */
    case CommercialUse = 'commercial_use';

    /** May adaptations of the work be shared, and under which terms? */
    case Modifications = 'modifications';
}
