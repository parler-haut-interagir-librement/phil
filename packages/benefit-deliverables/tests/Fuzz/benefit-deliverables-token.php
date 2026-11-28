<?php

declare(strict_types=1);

/*
 * The reading of download tokens nobody signed: each input gives the identifier of an export only when it is the
 * token signed for it, and `null` otherwise — never an exception.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\BenefitDeliverables\Export\DownloadLinks;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$links = new DownloadLinks(str_repeat('k', 32));
$at = new DateTimeImmutable('@1700000000');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($links, $at): void {
    $id = $links->verify($input, $at);

    if (null !== $id && $links->sign($id, new DateTimeImmutable('@'.explode('.', $input)[1])) !== $input) {
        throw new LogicException('A token that is not the one signed was taken.');
    }
});
