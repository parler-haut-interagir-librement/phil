<?php

declare(strict_types=1);

use Phil\SocialCardsBundle\Infrastructure\Http\SocialCardController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The cards of the pages, imported by the application:
 *
 *     # config/routes/phil_social_cards.yaml
 *     phil_social_cards:
 *         resource: '@PhilSocialCardsBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(SocialCardController::ROUTE, '/social-cards/{fingerprint}.png')
        ->controller(SocialCardController::class)
        ->requirements(['fingerprint' => '[0-9a-f]{32}'])
        ->methods(['GET', 'HEAD']);
};
