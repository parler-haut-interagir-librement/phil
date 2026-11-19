<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Unit\Fixture;

use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * A translator with the bundle's messages, in English and French.
 */
final class Translations
{
    public static function translator(string $locale = 'en'): Translator
    {
        $translator = new Translator($locale);
        $translator->addLoader('php', new PhpFileLoader());

        foreach (['en', 'fr'] as $language) {
            $translator->addResource('php', \dirname(__DIR__, 3).'/translations/phil_reservation+intl-icu.'.$language.'.php', $language, 'phil_reservation+intl-icu');
        }

        return $translator;
    }
}
