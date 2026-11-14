<?php

declare(strict_types=1);

use Phil\OnboardingBundle\Infrastructure\Http\OnboardingController;
use Phil\OnboardingBundle\Infrastructure\Http\Routes;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The endpoints of the checklist and the tour, imported by the application under a prefix of its choice, behind the
 * firewall of its users:
 *
 *     # config/routes/phil_onboarding.yaml
 *     phil_onboarding:
 *         resource: '@PhilOnboardingBundle/config/routes.php'
 *         prefix: /onboarding
 */
return static function (RoutingConfigurator $routes): void {
    $identifiers = ['flow' => Routes::IDENTIFIER, 'step' => Routes::IDENTIFIER];

    $routes->add(Routes::DONE, '/{flow}/{step}/done')
        ->controller([OnboardingController::class, 'done'])
        ->methods(['POST'])
        ->requirements($identifiers);
    $routes->add(Routes::ANSWER, '/{flow}/{step}/answer')
        ->controller([OnboardingController::class, 'answer'])
        ->methods(['POST'])
        ->requirements($identifiers);
    $routes->add(Routes::SKIP, '/{flow}/{step}/skip')
        ->controller([OnboardingController::class, 'skip'])
        ->methods(['POST'])
        ->requirements($identifiers);
    $routes->add(Routes::DISMISS, '/{flow}/dismiss')
        ->controller([OnboardingController::class, 'dismiss'])
        ->methods(['POST'])
        ->requirements(['flow' => Routes::IDENTIFIER]);
    $routes->add(Routes::RESET, '/{flow}/reset')
        ->controller([OnboardingController::class, 'reset'])
        ->methods(['POST'])
        ->requirements(['flow' => Routes::IDENTIFIER]);
};
