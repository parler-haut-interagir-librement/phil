<?php

declare(strict_types=1);

use Phil\BlockEditorBundle\Infrastructure\Http\Endpoints;
use Phil\BlockEditorBundle\Infrastructure\Http\ImageUploadController;
use Phil\BlockEditorBundle\Infrastructure\Http\ReferenceSearchController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The editor's endpoints, imported by the application under a prefix of its choice, behind its firewall:
 *
 *     # config/routes/phil_block_editor.yaml
 *     phil_block_editor:
 *         resource: '@PhilBlockEditorBundle/config/routes.php'
 *         prefix: /_block-editor
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(Endpoints::IMAGE, '/image')
        ->controller(ImageUploadController::class)
        ->methods(['POST']);
    $routes->add(Endpoints::MENTIONS, '/mentions')
        ->controller([ReferenceSearchController::class, 'mentions'])
        ->methods(['GET']);
    $routes->add(Endpoints::LINKS, '/links')
        ->controller([ReferenceSearchController::class, 'links'])
        ->methods(['GET']);
};
