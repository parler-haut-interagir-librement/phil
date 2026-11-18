<?php

declare(strict_types=1);

namespace Phil\Slides\Tests\Unit\Support;

use Phil\Slides\Application\DeckReader;
use Phil\Slides\Application\SlideRenderer;
use Phil\Slides\Domain\Slicer;
use Phil\Slides\Infrastructure\Twig\SlidesExtension;
use Phil\Ujo\Ujo;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

/**
 * A Twig environment as the bundle sets it up: the templates under `@PhilSlides`, the translations of the bundle in
 * English and French, and the extension as a runtime.
 */
final class Twig
{
    private function __construct()
    {
    }

    public static function environment(string $locale = 'en', string $fragmentClass = 'fragment'): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'PhilSlides');

        $translator = new Translator($locale);
        $translator->addLoader('php', new PhpFileLoader());

        foreach (['en', 'fr'] as $language) {
            $translator->addResource('php', \dirname(__DIR__, 3).'/translations/phil_slides.'.$language.'.php', $language, 'phil_slides');
        }

        $ujo = new Ujo();
        $extension = new SlidesExtension(new DeckReader(new Slicer(), $ujo), new SlideRenderer($ujo), $fragmentClass);
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension($translator));
        $twig->addExtension(new AttributeExtension(SlidesExtension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([SlidesExtension::class => static fn (): SlidesExtension => $extension]));

        return $twig;
    }
}
