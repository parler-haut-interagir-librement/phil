<?php

declare(strict_types=1);

/*
 * The front controller of the application the OpenID conformance suite plays against, served by FrankenPHP in the
 * `op` service of compose.yaml.
 */

use Phil\IdentityProviderBundle\Tests\Conformance\App\ConformanceKernel;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__, 5).'/vendor/autoload.php';

$issuer = getenv('PHIL_CONFORMANCE_ISSUER');
$root = getenv('PHIL_CONFORMANCE_ROOT');

if (!is_string($issuer) || !is_string($root)) {
    http_response_code(500);
    echo 'PHIL_CONFORMANCE_ISSUER and PHIL_CONFORMANCE_ROOT are not set.';

    return;
}

$kernel = new ConformanceKernel($issuer, $root);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
