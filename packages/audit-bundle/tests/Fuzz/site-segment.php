<?php

declare(strict_types=1);

/*
 * Site segments on segments and tenants nobody wrote: a site segment is built, or refused with an InvalidValue; one
 * built is a site segment of its tenant alone, short enough for the table, and gives back the segment it was built
 * on; and reading any name as a segment never throws nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\AuditBundle\Domain\Chain\SiteSegment;
use Phil\DddBundle\Domain\Exception\InvalidValue;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    // The input is a segment, then a tenant, split at the first line feed.
    [$segment, $tenant] = explode("\n", $input, 2) + [1 => ''];
    SiteSegment::base($input);

    try {
        $site = SiteSegment::of($segment, $tenant);
    } catch (InvalidValue) {
        // A segment or a tenant refused is an answer.
        return;
    }

    if (strlen($site) > 64 || !SiteSegment::isSite($site) || !SiteSegment::belongsTo($site, $tenant) || SiteSegment::belongsTo($site, $tenant.'x') || SiteSegment::base($site) !== $segment) {
        throw new LogicException('A site segment does not name its own site.');
    }
});
$config->setMaxLen(512);
