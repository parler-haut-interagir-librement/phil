<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\Finder;

/**
 * A clause of the custom terms the license finder writes when no license of the catalog fits. Each is translated as
 * `terms.<value>` in the domain `phil_licensing`, with `%holder%`.
 */
enum TermsClause: string
{
    /** Anyone may copy and share the work. */
    case Share = 'share';

    /** The work may be used for commercial purposes. */
    case CommercialUse = 'commercial_use';

    /** The work may not be used for commercial purposes. */
    case NoCommercialUse = 'no_commercial_use';

    /** Adaptations may be shared under any terms. */
    case Adaptations = 'adaptations';

    /** Adaptations may be shared under these same terms only. */
    case AdaptationsShareAlike = 'adaptations_share_alike';

    /** Adaptations may not be shared. */
    case NoAdaptations = 'no_adaptations';

    /** Whoever reuses the work credits its holder. */
    case Attribution = 'attribution';

    /** Whoever reuses the work need not credit its holder. */
    case NoAttribution = 'no_attribution';

    /** The work is offered as it is, without warranty. */
    case NoWarranty = 'no_warranty';
}
