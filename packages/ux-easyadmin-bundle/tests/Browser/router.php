<?php

declare(strict_types=1);

/*
 * The router of PHP's built-in server for the browser suite: the built files of EasyAdmin from where Composer installed
 * them, as `assets:install` would publish them, everything else through the application of the functional suite.
 */

use Phil\UxEasyAdminBundle\Tests\Functional\App\TestKernel;
use Symfony\Component\HttpFoundation\Request;

$package = dirname(__DIR__, 2);
$vendor = array_find([$package.'/../../vendor', $package.'/vendor'], static fn (string $directory): bool => is_file($directory.'/autoload.php'));

if (null === $vendor) {
    throw new RuntimeException('Run composer install first.');
}

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = is_string($uri) ? strtok($uri, '?') : false;
$types = ['css' => 'text/css', 'js' => 'text/javascript', 'woff2' => 'font/woff2'];

if (is_string($path) && 1 === preg_match('#^/bundles/easyadmin/((?:fonts/)?[\w.-]+\.(css|js|woff2))$#D', $path, $file)) {
    $public = $vendor.'/easycorp/easyadmin-bundle/public/'.$file[1];

    if (!is_file($public)) {
        http_response_code(404);

        return true;
    }

    header('Content-Type: '.$types[$file[2]]);
    readfile($public);

    return true;
}

require $vendor.'/autoload.php';

// In debug, so that AssetMapper serves the theme and the container follows the code the suite runs on.
$kernel = new TestKernel();
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);

return true;
