<?php

declare(strict_types=1);

use Phil\EmbedBundle\Application\EmbedCodes;
use Phil\EmbedBundle\Infrastructure\Http\EmbedScriptController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The script other sites load, imported under a prefix of the application's choice.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(EmbedCodes::SCRIPT_ROUTE, '/{file}')
        ->controller(EmbedScriptController::class)
        ->methods(['GET', 'HEAD'])
        ->requirements(['file' => 'phil-embed\.js|lib/message\.js'])
        ->stateless();
};
