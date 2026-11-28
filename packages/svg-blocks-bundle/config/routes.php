<?php

declare(strict_types=1);

use Phil\SvgBlocksBundle\Infrastructure\Http\PreviewController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The preview endpoint, imported by the application under a prefix of its choice, behind its firewall:
 *
 *     # config/routes/phil_svg_blocks.yaml
 *     phil_svg_blocks:
 *         resource: '@PhilSvgBlocksBundle/config/routes.php'
 *         prefix: /_svg-blocks
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('phil_svg_blocks_preview', '/preview')
        ->controller(PreviewController::class)
        ->methods(['POST']);
};
