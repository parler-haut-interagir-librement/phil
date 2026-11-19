<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\Catalog;

/**
 * What a license asks of whoever publishes a work under it, or uses material licensed under it.
 *
 * The obligations are summarized from the license texts, never quoted: the license itself, at its address, is what
 * binds.
 */
enum Requirement: string
{
    /** The creators of the material used are credited, as they ask. */
    case Attribution = 'attribution';

    /** A notice says the work is licensed under the license and where its text is found. */
    case LicenseNotice = 'license_notice';

    /** A copy of the full license text goes with every copy of the work. */
    case FullText = 'full_text';

    /** The copyright notices of the material used are kept, the work's own added to them. */
    case CopyrightNotice = 'copyright_notice';

    /** Adaptations of the licensed material are offered under the same license. */
    case ShareAlike = 'share_alike';

    /** Changes made to the licensed material are indicated. */
    case IndicateChanges = 'indicate_changes';

    /** The elements of the work kept out of the license are identified, as the ORC License's Reserved Material. */
    case ReservedMaterial = 'reserved_material';

    /** The Product Identity of the work is designated, as the Open Game License asks. */
    case ProductIdentity = 'product_identity';

    /** The portions of the work that are open content are clearly identified, as the Open Game License asks. */
    case DesignatedContent = 'designated_content';

    /** The licensed material is not used for commercial purposes, as the NonCommercial licenses of Creative Commons ask. */
    case NonCommercial = 'non_commercial';

    /** Adapted material is not shared, as the NoDerivatives licenses of Creative Commons ask. */
    case NoDerivatives = 'no_derivatives';
}
