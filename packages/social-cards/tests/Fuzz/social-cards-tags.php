<?php

declare(strict_types=1);

/*
 * The reader of tags on pages nobody wrote: whatever it reads, it returns tags, a preview and a check, and never throws.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\SocialCards\Preview\LinkPreview;
use Phil\SocialCards\Preview\TagCheck;
use Phil\SocialCards\Preview\TagReader;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new TagReader(65_536);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader): void {
    $tags = $reader->read($input, 'https://example.com/a/page');
    LinkPreview::fromTags($tags);
    TagCheck::check($tags);
});
$config->setMaxLen(16_384);
