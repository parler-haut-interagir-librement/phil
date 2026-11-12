<?php

declare(strict_types=1);

/*
 * The page `composer bench:worker phil/identity-client-bundle` measures: FrankenPHP keeps this script alive across
 * requests, so the kernel built here serves every request, as in an application. Each request is a page behind the
 * firewall of the bundle, the path of a session with nothing to refresh.
 */

use Phil\IdentityClientBundle\Tests\Functional\App\TestKernel;
use Symfony\Component\HttpFoundation\Request;

// The package's own vendor/ once split, the monorepo's while it is developed there.
$autoload = dirname(__DIR__, 2).'/vendor/autoload.php';
require is_file($autoload) ? $autoload : dirname(__DIR__, 4).'/vendor/autoload.php';

$kernel = new TestKernel();
$kernel->boot();

$page = static function () use ($kernel): void {
    $request = Request::create('/');
    $response = $kernel->handle($request);
    $response->send();

    $kernel->terminate($request, $response);
};

while (frankenphp_handle_request($page)) {
    gc_collect_cycles();
}
