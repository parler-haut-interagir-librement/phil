<?php

declare(strict_types=1);

use Phil\MapBundle\Tests\Functional\App\TestKernel;
use Symfony\Component\HttpFoundation\Request;

/*
 * The router of PHP's built-in server for the browser suite: the bundle's controller and stylesheet, Symfony UX Map's
 * Leaflet controller from where Composer installed it, Stimulus and Leaflet from where npm installed them; every
 * other request goes to the functional suite's application — the page of maps, and the tile proxy.
 */

$package = dirname(__DIR__, 2);
$vendor = array_find([dirname($package, 2).'/vendor', $package.'/vendor'], static fn (string $directory): bool => is_file($directory.'/autoload.php')) ?? $package.'/vendor';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = is_string($uri) ? (string) strtok($uri, '?') : '/';

$assets = [
    '/assets/stimulus.js' => __DIR__.'/node_modules/@hotwired/stimulus/dist/stimulus.js',
    '/assets/leaflet.js' => __DIR__.'/node_modules/leaflet/dist/leaflet-src.esm.js',
    '/assets/leaflet.css' => __DIR__.'/node_modules/leaflet/dist/leaflet.css',
    '/assets/ux-leaflet-map.js' => $vendor.'/symfony/ux-leaflet-map/assets/dist/map_controller.js',
    '/assets/map_controller.js' => $package.'/assets/src/map_controller.js',
    '/assets/lib/plan.js' => $package.'/assets/src/lib/plan.js',
    '/assets/map.css' => $package.'/assets/styles/map.css',
];

if (isset($assets[$path])) {
    header('Content-Type: '.(str_ends_with($path, '.css') ? 'text/css' : 'text/javascript'));
    readfile($assets[$path]);

    return true;
}

if ('/plans/hall.png' === $path) {
    header('Content-Type: image/png');
    $image = imagecreatetruecolor(360, 180);

    if ($image instanceof GdImage) {
        imagepng($image);
    }

    return true;
}

require $vendor.'/autoload.php';

$kernel = new TestKernel('test', true);
$response = $kernel->handle(Request::createFromGlobals());
$response->send();

return true;
