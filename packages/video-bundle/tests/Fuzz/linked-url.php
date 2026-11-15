<?php

declare(strict_types=1);

/*
 * An address nobody wrote, as an author would paste it: a linked video is built, or InvalidVideo thrown.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\VideoBundle\Application\Exception\InvalidVideo;
use Phil\VideoBundle\Application\LinkedVideo;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $video = LinkedVideo::fromUrl('v', 'Video', $input);

        if (!str_starts_with($video->embed(), 'https://www.youtube-nocookie.com/embed/') && !str_starts_with($video->embed(), 'https://player.vimeo.com/video/')) {
            throw new LogicException("The player of a linked video is the platform's player without cookies.");
        }
    } catch (InvalidVideo) {
        // The error the bundle promises for an address it refuses.
    }
});
$config->setMaxLen(2048);
