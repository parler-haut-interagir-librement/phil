<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Support;

use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Contract\LicenseDeclarationRepository;
use Phil\LicensingBundle\Infrastructure\Translation\LicenseNames;
use Phil\LicensingBundle\Infrastructure\Twig\AttributionRenderer;
use Phil\LicensingBundle\Infrastructure\Twig\LicensingExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

/**
 * A Twig environment as the bundle sets it up: the templates under `@PhilLicensing`, the translations of the bundle in
 * English and French, and the extension as a runtime.
 */
final class Twig
{
    private function __construct()
    {
    }

    public static function translator(string $locale = 'en'): Translator
    {
        $translator = new Translator($locale);
        $translator->addLoader('php', new PhpFileLoader());

        foreach (['en', 'fr'] as $language) {
            $translator->addResource('php', \dirname(__DIR__, 3).'/translations/phil_licensing.'.$language.'.php', $language, LicenseNames::DOMAIN);
        }

        return $translator;
    }

    /**
     * @return array{Environment, AttributionRenderer}
     */
    public static function environment(LicenseDeclarationRepository $declarations, string $locale = 'en'): array
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'PhilLicensing');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $catalog = new LicenseCatalog();
        $renderer = new AttributionRenderer($twig, $declarations, $catalog);
        $extension = new LicensingExtension($renderer, new LicenseNames($catalog, self::translator($locale)));
        $twig->addExtension(new TranslationExtension(self::translator($locale)));
        $twig->addExtension(new AttributeExtension(LicensingExtension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([LicensingExtension::class => static fn (): LicensingExtension => $extension]));

        return [$twig, $renderer];
    }
}
