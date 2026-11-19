<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\Compliance;

/**
 * Why a work cannot be published as it is declared. Each code is translated as `violation.<code>` in the domain
 * `phil_licensing`, with `%license%` and `%resource%`.
 */
enum ViolationCode: string
{
    /** The work has no license declaration. */
    case NoDeclaration = 'no_declaration';

    /** The work or a resource names a license the catalog does not hold. */
    case UnknownLicense = 'unknown_license';

    /** The license asks for its full text, or for terms no address gives, and the declaration holds none. */
    case MissingLicenseText = 'missing_license_text';

    /** The license asks for the work's own copyright notice, and the declaration has none. */
    case MissingNotice = 'missing_notice';

    /** The ORC License asks for the Reserved Material to be identified. */
    case MissingReservedMaterial = 'missing_reserved_material';

    /** The Open Game License asks for the Product Identity to be designated. */
    case MissingProductIdentity = 'missing_product_identity';

    /** The Open Game License asks for the open content to be identified. */
    case MissingDesignatedContent = 'missing_designated_content';

    /** The license of a resource asks for its creators to be credited, and the attribution names nobody. */
    case MissingCredit = 'missing_credit';

    /** The license of a resource asks for its copyright notice to be kept, and the attribution has none. */
    case MissingResourceNotice = 'missing_resource_notice';

    /** The license of a resource asks for the changes made to it to be indicated, and the attribution says nothing of them. */
    case MissingChanges = 'missing_changes';

    /** A resource is under a share-alike license other than the work's. */
    case ShareAlikeConflict = 'share_alike_conflict';

    public function messageKey(): string
    {
        return 'violation.'.$this->value;
    }
}
