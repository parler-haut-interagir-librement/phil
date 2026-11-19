<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit\Fixture;

use Phil\AiBundle\Application\PromptTemplates;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

/**
 * Prompts in English and French, as an application would write them, in Symfony's translator.
 */
final class Prompts
{
    private function __construct()
    {
    }

    public static function translator(): Translator
    {
        $translator = new Translator('en');
        $translator->setFallbackLocales(['en']);
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'draft.rewrite.system' => 'You edit {genre} prose.',
            'draft.rewrite.user' => 'Rewrite: {text}',
            'title.suggest.user' => 'Suggest a title for: {text}',
        ], 'en', PromptTemplates::DEFAULT_DOMAIN);
        $translator->addResource('array', ['draft.rewrite.user' => 'Réécris : {text}'], 'fr', PromptTemplates::DEFAULT_DOMAIN);

        return $translator;
    }

    public static function templates(): PromptTemplates
    {
        return new PromptTemplates(self::translator());
    }
}
