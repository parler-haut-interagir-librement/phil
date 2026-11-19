<?php

declare(strict_types=1);

/*
 * The classifier of visits on headers nobody wrote — the purpose, then a "|", then the User-Agent —: whatever they
 * read, it answers a class, the same class for the same headers, a prefetch whenever the purpose names one, and nothing
 * throws.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\ShortLinkBundle\Application\Visitor\KnownVisitorClassifier;
use Phil\ShortLinkBundle\Domain\Enum\VisitorClass;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$classifier = new KnownVisitorClassifier();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($classifier): void {
    [$purpose, $userAgent] = str_contains($input, '|') ? explode('|', $input, 2) : [null, $input];
    $class = $classifier->classify($userAgent, $purpose);

    if ($class !== $classifier->classify($userAgent, $purpose)) {
        throw new LogicException('The same headers were classified twice differently.');
    }

    if ('prefetch' === $purpose && VisitorClass::Prefetch !== $class) {
        throw new LogicException('A prefetch was not told as one.');
    }

    if (VisitorClass::Prefetch === $class && (null === $purpose || '' === trim($purpose))) {
        throw new LogicException('A request without a purpose was told as a prefetch.');
    }
});
$config->setMaxLen(1024);
