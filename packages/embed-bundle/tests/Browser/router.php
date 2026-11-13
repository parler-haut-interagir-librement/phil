<?php

declare(strict_types=1);

use Phil\EmbedBundle\Tests\Functional\App\TestKernel;
use Symfony\Component\HttpFoundation\Request;

/*
 * The router of PHP's built-in server for the browser suite, which runs it twice: on the port of TestKernel::HOST, a
 * partner's page with the code of an embed and a forged one; on the port of TestKernel::SITE, the functional suite's
 * application — the embedded page, the script under its stable address — and the frame controller with Stimulus.
 */

$package = dirname(__DIR__, 2);
$vendor = array_find([dirname($package, 2).'/vendor', $package.'/vendor'], static fn (string $directory): bool => is_file($directory.'/autoload.php')) ?? $package.'/vendor';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = is_string($uri) ? (string) strtok($uri, '?') : '/';

$assets = [
    '/assets/stimulus.js' => __DIR__.'/node_modules/@hotwired/stimulus/dist/stimulus.js',
    '/assets/frame_controller.js' => $package.'/assets/src/frame_controller.js',
    '/assets/lib/message.js' => $package.'/assets/src/lib/message.js',
];

if (isset($assets[$path])) {
    header('Content-Type: text/javascript');
    readfile($assets[$path]);

    return true;
}

require $vendor.'/autoload.php';

$kernel = new TestKernel('test', true);
$port = $_SERVER['SERVER_PORT'] ?? '';
$port = is_numeric($port) ? (int) $port : 0;

if (str_ends_with(TestKernel::HOST, ':'.$port)) {
    // The partner's page, written by the site as the site would give its code, served from the partner's origin.
    $kernel->handle(Request::create(TestKernel::SITE.'/host'))->send();

    return true;
}

$kernel->handle(Request::createFromGlobals())->send();

return true;
