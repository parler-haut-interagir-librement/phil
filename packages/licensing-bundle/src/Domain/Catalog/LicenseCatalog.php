<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\Catalog;

use Phil\LicensingBundle\Domain\Exception\InvalidLicensing;
use Phil\LicensingBundle\Domain\Exception\UnknownLicense;
use Phil\LicensingBundle\Domain\ValueObject\License;

/**
 * The licenses a work may be declared under: the built-in ones, then those of the application.
 *
 * The requirements of the built-in licenses summarize their official texts:
 *
 * - the ORC License asks for the ORC Notice, the attribution of the Licensed Material used, the identification of the
 *   Reserved Material, and adaptations of Licensed Material offered under it; its text need not be reproduced;
 * - Creative Commons Attribution 4.0 asks for the credit of the creators, a reference to the license and an indication
 *   of changes; ShareAlike adds adaptations under the same license; NonCommercial keeps the material out of commercial
 *   use; NoDerivatives keeps adapted material from being shared; a link to the license suffices;
 * - CC0 1.0 dedicates the work to the public domain and asks nothing;
 * - the Open Game License 1.0a asks for a copy of its text with every copy, the copyright notices of the Open Game
 *   Content used with the work's own, the designation of Product Identity and the identification of the Open Game
 *   Content;
 * - "custom" is the work's own terms, which the declaration holds as its license text.
 */
final readonly class LicenseCatalog
{
    public const string ORC = 'orc';

    public const string CC_BY = 'cc-by-4.0';

    public const string CC_BY_SA = 'cc-by-sa-4.0';

    public const string CC_BY_NC = 'cc-by-nc-4.0';

    public const string CC_BY_NC_SA = 'cc-by-nc-sa-4.0';

    public const string CC_BY_ND = 'cc-by-nd-4.0';

    public const string CC_BY_NC_ND = 'cc-by-nc-nd-4.0';

    public const string CC0 = 'cc0-1.0';

    public const string OGL = 'ogl-1.0a';

    public const string CUSTOM = 'custom';

    /** @var array<string, License> */
    private array $licenses;

    /**
     * @param list<License> $licenses the application's own, after the built-in ones
     *
     * @throws InvalidLicensing when a code is given twice
     */
    public function __construct(array $licenses = [])
    {
        $all = [];

        foreach ([...self::builtIn(), ...$licenses] as $license) {
            if (isset($all[$license->code])) {
                throw InvalidLicensing::because(\sprintf('The license "%s" is in the catalog already.', $license->code));
            }

            $all[$license->code] = $license;
        }

        $this->licenses = $all;
    }

    /**
     * @return list<License>
     */
    public static function builtIn(): array
    {
        return [
            License::of(self::ORC, 'ORC License', [Requirement::LicenseNotice, Requirement::Attribution, Requirement::ReservedMaterial, Requirement::ShareAlike], 'https://paizo.com/orclicense', 'Library of Congress TX 9-307-067'),
            License::of(self::CC_BY, 'Creative Commons Attribution 4.0 International', [Requirement::LicenseNotice, Requirement::Attribution, Requirement::CopyrightNotice, Requirement::IndicateChanges], 'https://creativecommons.org/licenses/by/4.0/', spdx: 'CC-BY-4.0'),
            License::of(self::CC_BY_SA, 'Creative Commons Attribution-ShareAlike 4.0 International', [Requirement::LicenseNotice, Requirement::Attribution, Requirement::CopyrightNotice, Requirement::IndicateChanges, Requirement::ShareAlike], 'https://creativecommons.org/licenses/by-sa/4.0/', spdx: 'CC-BY-SA-4.0'),
            License::of(self::CC_BY_NC, 'Creative Commons Attribution-NonCommercial 4.0 International', [Requirement::LicenseNotice, Requirement::Attribution, Requirement::CopyrightNotice, Requirement::IndicateChanges, Requirement::NonCommercial], 'https://creativecommons.org/licenses/by-nc/4.0/', spdx: 'CC-BY-NC-4.0'),
            License::of(self::CC_BY_NC_SA, 'Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International', [Requirement::LicenseNotice, Requirement::Attribution, Requirement::CopyrightNotice, Requirement::IndicateChanges, Requirement::ShareAlike, Requirement::NonCommercial], 'https://creativecommons.org/licenses/by-nc-sa/4.0/', spdx: 'CC-BY-NC-SA-4.0'),
            License::of(self::CC_BY_ND, 'Creative Commons Attribution-NoDerivatives 4.0 International', [Requirement::LicenseNotice, Requirement::Attribution, Requirement::CopyrightNotice, Requirement::IndicateChanges, Requirement::NoDerivatives], 'https://creativecommons.org/licenses/by-nd/4.0/', spdx: 'CC-BY-ND-4.0'),
            License::of(self::CC_BY_NC_ND, 'Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International', [Requirement::LicenseNotice, Requirement::Attribution, Requirement::CopyrightNotice, Requirement::IndicateChanges, Requirement::NonCommercial, Requirement::NoDerivatives], 'https://creativecommons.org/licenses/by-nc-nd/4.0/', spdx: 'CC-BY-NC-ND-4.0'),
            License::of(self::CC0, 'CC0 1.0 Universal', [], 'https://creativecommons.org/publicdomain/zero/1.0/', spdx: 'CC0-1.0'),
            License::of(self::OGL, 'Open Game License 1.0a', [Requirement::FullText, Requirement::CopyrightNotice, Requirement::ShareAlike, Requirement::ProductIdentity, Requirement::DesignatedContent], 'https://opengamingfoundation.org/ogl.html'),
            License::of(self::CUSTOM, 'Custom license', [Requirement::LicenseNotice]),
        ];
    }

    public function find(string $code): ?License
    {
        return $this->licenses[$code] ?? null;
    }

    /**
     * @throws UnknownLicense
     */
    public function get(string $code): License
    {
        return $this->find($code) ?? throw UnknownLicense::named($code);
    }

    /**
     * @return list<License>
     */
    public function all(): array
    {
        return array_values($this->licenses);
    }
}
