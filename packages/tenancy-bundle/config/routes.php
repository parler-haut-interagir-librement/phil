<?php

declare(strict_types=1);

use Phil\TenancyBundle\Infrastructure\Http\SwitchActiveTenant;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

// The switch the selector posts to; import it with a prefix of your choice.
return static function (RoutingConfigurator $routes): void {
    $routes->add(SwitchActiveTenant::ROUTE, '/tenant/switch')->controller(SwitchActiveTenant::class)->methods(['POST']);
};
