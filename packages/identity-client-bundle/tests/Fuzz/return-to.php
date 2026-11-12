<?php

declare(strict_types=1);

/*
 * The return_to policy on inputs nobody wrote: whatever it accepts is a path of the application or an address on a
 * declared origin, and it never throws.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\IdentityClientBundle\Infrastructure\Security\DeclaredOriginsReturnToPolicy;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$policy = new DeclaredOriginsReturnToPolicy(['https://app.example.test']);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($policy): void {
    $target = $policy->resolve($input);
    if (null === $target || str_starts_with($target, 'https://app.example.test/')) {
        return;
    }

    if (!str_starts_with($target, '/') || str_starts_with($target, '//') || str_contains($target, '\\')) {
        throw new LogicException('An address that leaves the application was accepted: '.$target);
    }
});
$config->setMaxLen(2100);
