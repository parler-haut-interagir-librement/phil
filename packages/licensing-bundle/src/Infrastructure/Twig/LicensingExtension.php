<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Infrastructure\Twig;

use Phil\LicensingBundle\Application\AttributionPage;
use Phil\LicensingBundle\Domain\Contract\Licensable;
use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\License;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;
use Phil\LicensingBundle\Domain\ValueObject\Violation;
use Phil\LicensingBundle\Infrastructure\Translation\LicenseNames;
use Twig\Attribute\AsTwigFunction;

/**
 * What templates use of the licensing, declared by attributes and run as a Twig runtime:
 *
 * - `phil_licensing_attribution(subject, locale)`, the license and attribution page of a work, as HTML;
 * - `phil_licensing_page(subject)`, the same page as an `AttributionPage`, for a template of the application;
 * - `phil_licensing_license_name(license, locale)`, the name of a license in the reader's language;
 * - `phil_licensing_violation(violation, locale)`, the message of a violation of the compliance check.
 *
 * A subject is a `SubjectReference`, a `Licensable` object or a `LicenseDeclaration`.
 */
final readonly class LicensingExtension
{
    public function __construct(
        private AttributionRenderer $renderer,
        private LicenseNames $names,
    ) {
    }

    #[AsTwigFunction(name: 'phil_licensing_attribution', isSafe: ['html'])]
    public function attribution(SubjectReference|Licensable|LicenseDeclaration $subject, ?string $locale = null): string
    {
        return $this->renderer->html($subject, $locale);
    }

    #[AsTwigFunction(name: 'phil_licensing_page')]
    public function page(SubjectReference|Licensable|LicenseDeclaration $subject): ?AttributionPage
    {
        return $this->renderer->page($subject);
    }

    #[AsTwigFunction(name: 'phil_licensing_license_name')]
    public function licenseName(License|string $license, ?string $locale = null): string
    {
        return $this->names->name($license, $locale);
    }

    #[AsTwigFunction(name: 'phil_licensing_violation')]
    public function violation(Violation $violation, ?string $locale = null): string
    {
        return $this->names->violation($violation, $locale);
    }
}
