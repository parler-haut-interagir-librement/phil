<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Infrastructure\Twig;

use Phil\LicensingBundle\Application\AttributionPage;
use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Contract\Licensable;
use Phil\LicensingBundle\Domain\Contract\LicenseDeclarationRepository;
use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;
use Twig\Environment;

/**
 * The license and attribution page of a work as an HTML fragment — a `<section>` without a document around it —, for a
 * page of the site or a printed book laid out from HTML.
 */
final readonly class AttributionRenderer
{
    public const string TEMPLATE = '@PhilLicensing/attribution.html.twig';

    public function __construct(
        private Environment $twig,
        private LicenseDeclarationRepository $declarations,
        private LicenseCatalog $catalog,
        private string $template = self::TEMPLATE,
    ) {
    }

    /**
     * The page of a subject, or `null` when it has no declaration.
     */
    public function page(SubjectReference|Licensable|LicenseDeclaration $subject): ?AttributionPage
    {
        $declaration = $this->declaration($subject);

        return $declaration instanceof LicenseDeclaration ? AttributionPage::of($declaration, $this->catalog) : null;
    }

    /**
     * The fragment of a subject in the given locale, else the translator's; an empty string when it has no declaration.
     */
    public function html(SubjectReference|Licensable|LicenseDeclaration $subject, ?string $locale = null): string
    {
        $page = $this->page($subject);

        return $page instanceof AttributionPage ? $this->twig->render($this->template, ['page' => $page, 'locale' => $locale]) : '';
    }

    private function declaration(SubjectReference|Licensable|LicenseDeclaration $subject): ?LicenseDeclaration
    {
        if ($subject instanceof LicenseDeclaration) {
            return $subject;
        }

        return $this->declarations->declarationOf($subject instanceof Licensable ? $subject->licenseSubject() : $subject);
    }
}
