<?php

declare(strict_types=1);

/*
 * The answer to a preflight on headers nobody wrote: the input is the Origin, the requested method and the requested
 * headers, separated by line feeds. The answer is always 204 and varies on Origin; it allows the origin only when the
 * policy allows it, never with `*` beside credentials, and never another exception.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\CorsBundle\Application\CorsHeaders;
use Phil\CorsBundle\Domain\CorsPolicy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$headers = new CorsHeaders();
$policies = [
    new CorsPolicy(['https://app.example.com'], ['^https://[a-z0-9-]+\.example\.com$'], ['GET', 'PUT'], ['Content-Type'], allowCredentials: true, maxAge: 60),
    new CorsPolicy(['*'], allowHeaders: ['*'], allowPrivateNetwork: true),
];

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($headers, $policies): void {
    [$origin, $method, $requested] = explode("\n", $input, 3) + ['', '', ''];
    $request = Request::create('/api', 'OPTIONS');
    $request->headers->set('Origin', $origin);
    $request->headers->set('Access-Control-Request-Method', $method);
    $request->headers->set('Access-Control-Request-Headers', $requested);

    foreach ($policies as $policy) {
        $response = $headers->preflight($request, $policy);
        $allowed = $response->headers->get('Access-Control-Allow-Origin');

        if (Response::HTTP_NO_CONTENT !== $response->getStatusCode() || !in_array('Origin', $response->getVary(), true)) {
            throw new LogicException('A preflight answered without 204 or Vary: Origin.');
        }

        if (null !== $allowed && (!$policy->allowsOrigin($origin) || ('*' === $allowed && $policy->allowCredentials))) {
            throw new LogicException('An origin allowed that the policy refuses, or a wildcard with credentials.');
        }
    }
});
$config->setMaxLen(8192);
