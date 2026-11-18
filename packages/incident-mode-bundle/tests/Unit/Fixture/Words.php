<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Unit\Fixture;

use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Symfony's translator with the bundle's translations, in the locale given.
 */
final class Words
{
    private function __construct()
    {
    }

    public static function translator(string $locale = 'en'): Translator
    {
        $translator = new Translator($locale);
        $translator->addLoader('php', new PhpFileLoader());

        foreach (['en', 'fr'] as $language) {
            $translator->addResource('php', \dirname(__DIR__, 3).'/translations/phil_incident_mode.'.$language.'.php', $language, 'phil_incident_mode');
        }

        return $translator;
    }
}
