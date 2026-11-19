<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Support;

use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Finder\LicenseFinder;
use Phil\LicensingBundle\Infrastructure\Form\AttributionType;
use Phil\LicensingBundle\Infrastructure\Form\Finder\LicenseFinderStepType;
use Phil\LicensingBundle\Infrastructure\Form\Finder\LicenseFinderType;
use Phil\LicensingBundle\Infrastructure\Translation\LicenseNames;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms as FormFactories;
use Symfony\Component\Validator\Validation;

/**
 * A form factory as the bundle sets it up: the validator, and the bundle's types with the catalog and the translations
 * of the given locale.
 */
final class Forms
{
    private function __construct()
    {
    }

    public static function factory(string $locale = 'en', ?LicenseCatalog $catalog = null): FormFactoryInterface
    {
        $catalog ??= new LicenseCatalog();
        $names = new LicenseNames($catalog, Twig::translator($locale));

        return FormFactories::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidatorBuilder()->setTranslator(Twig::translator($locale))->getValidator()))
            ->addType(new AttributionType($catalog, $names))
            ->addType(new LicenseFinderStepType(new LicenseFinder($catalog), $names))
            ->addType(new LicenseFinderType())
            ->getFormFactory();
    }
}
