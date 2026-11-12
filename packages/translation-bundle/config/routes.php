<?php

declare(strict_types=1);

use Phil\TranslationBundle\Infrastructure\Http\GlossaryController;
use Phil\TranslationBundle\Infrastructure\Http\Routes;
use Phil\TranslationBundle\Infrastructure\Http\TranslationController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The translation screen and the glossary pages, imported by the application under a prefix of its choice, behind its
 * firewall:
 *
 *     # config/routes/phil_translation.yaml
 *     phil_translation:
 *         resource: '@PhilTranslationBundle/config/routes.php'
 *         prefix: /translate
 */
return static function (RoutingConfigurator $routes): void {
    $requirements = ['document' => Routes::DOCUMENT, 'language' => Routes::LANGUAGE];

    $routes->add(Routes::PAGE, '/{document}/{language}')->controller([TranslationController::class, 'page'])->methods(['GET'])->requirements($requirements);
    $routes->add(Routes::SAVE, '/{document}/{language}/segment')->controller([TranslationController::class, 'save'])->methods(['POST'])->requirements($requirements);
    $routes->add(Routes::COMMENT, '/{document}/{language}/comment')->controller([TranslationController::class, 'comment'])->methods(['POST'])->requirements($requirements);
    $routes->add(Routes::MACHINE, '/{document}/{language}/machine')->controller([TranslationController::class, 'machine'])->methods(['POST'])->requirements($requirements);
    $routes->add(Routes::EXPORT, '/{document}/{language}/xliff')->controller([TranslationController::class, 'export'])->methods(['GET'])->requirements($requirements);
    $routes->add(Routes::IMPORT, '/{document}/{language}/xliff')->controller([TranslationController::class, 'import'])->methods(['POST'])->requirements($requirements);

    // After the screen's routes: a document named `glossary` keeps its screen, and the words of the screen's routes
    // are no languages.
    $pair = ['source' => Routes::LANGUAGE, 'target' => Routes::LANGUAGE];

    $routes->add(Routes::GLOSSARY, '/glossary/{source}/{target}')->controller([GlossaryController::class, 'page'])->methods(['GET'])->requirements($pair);
    $routes->add(Routes::GLOSSARY_SAVE, '/glossary/{source}/{target}')->controller([GlossaryController::class, 'save'])->methods(['POST'])->requirements($pair);
    $routes->add(Routes::GLOSSARY_EXPORT, '/glossary/{source}/{target}/tbx')->controller([GlossaryController::class, 'export'])->methods(['GET'])->requirements($pair);
    $routes->add(Routes::GLOSSARY_IMPORT, '/glossary/{source}/{target}/tbx')->controller([GlossaryController::class, 'import'])->methods(['POST'])->requirements($pair);
};
