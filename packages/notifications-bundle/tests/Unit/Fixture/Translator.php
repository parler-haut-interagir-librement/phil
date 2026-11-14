<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator as SymfonyTranslator;

/**
 * A translator with the templates of two types in English and French.
 */
final class Translator
{
    public static function make(): SymfonyTranslator
    {
        $translator = new SymfonyTranslator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'comment.replied.title' => '%actor% replied to %subject%',
            'comment.replied.body' => '%count% replies on event %event%',
            'custom.title' => 'Custom title',
            'custom.body' => 'Custom body',
            'digest.subject.daily' => '%count% today',
        ], 'en', 'phil_notifications');
        $translator->addResource('array', [
            'comment.replied.title' => '%actor% a répondu à %subject%',
            'digest.subject.daily' => '%count% aujourd’hui',
        ], 'fr', 'phil_notifications');

        return $translator;
    }
}
