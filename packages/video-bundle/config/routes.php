<?php

declare(strict_types=1);

use Phil\VideoBundle\Infrastructure\Http\ProgressController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The route of the viewings the player sends, imported by the application when tracking is on:
 *
 *     # config/routes/phil_video.yaml
 *     phil_video:
 *         resource: '@PhilVideoBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('phil_video_progress', '/_phil/video/progress')
        ->controller(ProgressController::class)
        ->methods(['POST'])
        ->stateless();
};
