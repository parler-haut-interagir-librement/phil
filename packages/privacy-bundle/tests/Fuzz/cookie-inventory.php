<?php

declare(strict_types=1);

/*
 * The cookie inventory on files nobody wrote, decoded from JSON as YAML would give them: an inventory or a refusal,
 * never another error nor a warning.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\PrivacyBundle\Domain\Consent\ConsentCategory;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Cookie\CookieInventory;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$policy = new ConsentPolicy('1', [new ConsentCategory('analytics'), new ConsentCategory('videos')]);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($policy): void {
    $data = json_decode($input, true, 8);

    if (!is_array($data)) {
        return;
    }

    try {
        $inventory = CookieInventory::fromArray($data, $policy);
    } catch (InvalidPrivacyValue) {
        return;
    }

    foreach ($inventory->cookies as $cookie) {
        $cookie->matches($cookie->name);
        $cookie->purpose('en');
    }

    $inventory->groups($policy);
});
