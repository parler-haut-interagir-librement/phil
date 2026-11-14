<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Support;

use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * A translator holding the bundle's own translations, in English and French.
 */
final class Translations
{
    private function __construct()
    {
    }

    public static function translator(string $locale = 'en'): Translator
    {
        $translator = new Translator($locale);
        $translator->setFallbackLocales(['en']);
        $translator->addLoader('php', new PhpFileLoader());

        foreach (['en', 'fr'] as $language) {
            $translator->addResource('php', __DIR__.'/../../../translations/phil_certificates.'.$language.'.php', $language, 'phil_certificates');
        }

        return $translator;
    }
}
