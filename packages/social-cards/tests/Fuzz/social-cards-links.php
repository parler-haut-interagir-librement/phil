<?php

declare(strict_types=1);

/*
 * The finder of the links of texts members write: whatever the text, it returns at most the links asked for, each
 * once, each a piece of the text that starts with http:// or https://, and never throws.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\SocialCards\Preview\LinkFinder;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $links = LinkFinder::find($input, 3);

    if (count($links) > 3 || count(array_unique($links)) !== count($links)) {
        throw new LogicException('Too many links, or one twice.');
    }

    foreach ($links as $link) {
        if (1 !== preg_match('~^https?://~i', $link) || strlen($link) > LinkFinder::MAX_LINK || !mb_check_encoding($link, 'UTF-8')) {
            throw new LogicException(sprintf('"%s" is not a link.', $link));
        }
    }
});
$config->setMaxLen(8192);
