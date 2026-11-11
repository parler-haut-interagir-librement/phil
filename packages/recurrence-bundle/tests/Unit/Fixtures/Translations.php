<?php

declare(strict_types=1);

namespace Phil\RecurrenceBundle\Tests\Unit\Fixtures;

use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * A translator with the bundle's messages in English and French, as an application has them.
 */
final class Translations
{
    public static function translator(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('php', new PhpFileLoader());

        foreach (['en', 'fr'] as $locale) {
            $translator->addResource('php', __DIR__.'/../../../translations/phil_recurrence+intl-icu.'.$locale.'.php', $locale, 'phil_recurrence+intl-icu');
        }

        return $translator;
    }
}
