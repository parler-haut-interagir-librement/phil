<?php

declare(strict_types=1);

use Phil\ReactionsBundle\Infrastructure\Http\ReactionController;
use Phil\ReactionsBundle\Infrastructure\Http\ReactionGuard;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The reactions of a browser without JavaScript, by plain forms.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(ReactionGuard::ROUTE, '/submit')
        ->controller(ReactionController::class)
        ->methods(['POST']);
};
