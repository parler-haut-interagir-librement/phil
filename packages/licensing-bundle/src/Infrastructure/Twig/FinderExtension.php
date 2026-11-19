<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Infrastructure\Twig;

use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Finder\CustomTerms;
use Phil\LicensingBundle\Domain\Finder\Recommendation;
use Phil\LicensingBundle\Infrastructure\Translation\CustomTermsWriter;
use Phil\LicensingBundle\Infrastructure\Translation\LicenseNames;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

/**
 * What templates use of the license finder, declared by attributes and run as a Twig runtime:
 *
 * - `phil_licensing_recommendation(recommendation, holder, locale)`, the license the finder recommends, as an HTML
 *   fragment with the text of custom terms;
 * - `phil_licensing_terms(terms, holder, locale)`, the text of custom terms.
 */
final readonly class FinderExtension
{
    public const string TEMPLATE = '@PhilLicensing/finder/recommendation.html.twig';

    public function __construct(
        private Environment $twig,
        private LicenseCatalog $catalog,
        private LicenseNames $names,
        private CustomTermsWriter $writer,
    ) {
    }

    #[AsTwigFunction(name: 'phil_licensing_recommendation', isSafe: ['html'])]
    public function recommendation(Recommendation $recommendation, string $holder = '', ?string $locale = null): string
    {
        return $this->twig->render(self::TEMPLATE, [
            'recommendation' => $recommendation,
            'license' => $this->catalog->find($recommendation->license),
            'license_name' => $this->names->name($recommendation->license, $locale),
            'terms' => $recommendation->terms instanceof CustomTerms ? $this->writer->write($recommendation->terms, $holder, $locale) : null,
            'locale' => $locale,
        ]);
    }

    #[AsTwigFunction(name: 'phil_licensing_terms')]
    public function terms(CustomTerms $terms, string $holder = '', ?string $locale = null): string
    {
        return $this->writer->write($terms, $holder, $locale);
    }
}
