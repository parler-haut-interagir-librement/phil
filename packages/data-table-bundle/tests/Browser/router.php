<?php

declare(strict_types=1);

/*
 * The router of PHP's built-in server for the browser suite: the scripts and the stylesheet of the page from where
 * they are installed, everything else through the application of the functional suite.
 */

use Phil\DataTableBundle\Tests\Functional\App\TestKernel;
use Symfony\Component\HttpFoundation\Request;

$package = dirname(__DIR__, 2);
$vendor = array_find([$package.'/../../vendor', $package.'/vendor'], static fn (string $directory): bool => is_file($directory.'/autoload.php'));

if (null === $vendor) {
    throw new RuntimeException('Run composer install first.');
}

$assets = [
    '/assets/stimulus.js' => [__DIR__.'/node_modules/@hotwired/stimulus/dist/stimulus.js', 'text/javascript'],
    '/assets/live_controller.js' => [$vendor.'/symfony/ux-live-component/assets/dist/live_controller.js', 'text/javascript'],
    '/assets/table_controller.js' => [$package.'/assets/src/table_controller.js', 'text/javascript'],
    '/assets/data-table.css' => [$package.'/assets/styles/data-table.css', 'text/css'],
];
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = is_string($uri) ? strtok($uri, '?') : false;

if (is_string($path) && isset($assets[$path])) {
    [$file, $type] = $assets[$path];
    header('Content-Type: '.$type);
    readfile($file);

    return true;
}

require $vendor.'/autoload.php';

// In debug, so that the container follows the code the suite runs on.
$kernel = new TestKernel('test', true);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);

return true;
