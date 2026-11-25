<?php

declare(strict_types=1);

/*
 * Boots the plain kernel in a process of its own, where the classes of phil/monitoring-bundle cannot be loaded, as in
 * an application that does not require it; prints the services the test reads.
 */
use Phil\ErrorTrackingBundle\Tests\Functional\Plain\PlainKernel;
use Phil\MonitoringBundle\Domain\Contract\HealthCheck;

$autoload = $argv[1] ?? throw new InvalidArgumentException('The path of the autoloader is missing.');
require $autoload;

$loaders = spl_autoload_functions();

foreach ($loaders as $loader) {
    spl_autoload_unregister($loader);
}

spl_autoload_register(static function (string $class) use ($loaders): void {
    if (str_starts_with($class, 'Phil\\MonitoringBundle\\')) {
        return;
    }

    foreach ($loaders as $loader) {
        $loader($class);

        if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false) || enum_exists($class, false)) {
            return;
        }
    }
});

$kernel = new PlainKernel('hidden', true);
$kernel->boot();
echo json_encode([
    'monitoring' => interface_exists(HealthCheck::class),
    'services' => $kernel->getContainer()->getParameter(PlainKernel::PARAMETER),
], \JSON_THROW_ON_ERROR);
$kernel->shutdown();
