<?php

declare(strict_types=1);

/*
 * The front of the application the browser suite drives, served by PHP's built-in server; AssetMapper's development
 * server answers the requests for /assets.
 */

use Phil\FormsBundle\Tests\Browser\App\BrowserKernel;
use Symfony\Component\HttpFoundation\Request;

$root = dirname(__DIR__, 3);
$monorepo = dirname($root, 2);
require (is_file($monorepo.'/vendor/autoload.php') ? $monorepo : $root).'/vendor/autoload.php';

$kernel = new BrowserKernel('browser', true);
$kernel->install();
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
