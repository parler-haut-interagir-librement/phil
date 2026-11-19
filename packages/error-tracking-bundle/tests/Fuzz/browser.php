<?php

declare(strict_types=1);

use Phil\ErrorTrackingBundle\Application\BrowserErrorParser;
use Phil\ErrorTrackingBundle\Application\Exception\InvalidBrowserError;

/*
 * The parser of the browsers' reports on what nobody wrote. A body is read or refused with `InvalidBrowserError`,
 * nothing else, and every report read keeps within the bounds the parser promises: an http page of 2,048 bytes at most
 * without control character, a JavaScript name as its type, a message without control character, a bounded number of
 * frames, each from an http script without query nor fragment.
 */
$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $error = new BrowserErrorParser(5)->parse($input);
    } catch (InvalidBrowserError) {
        return;
    }

    if (strlen($error->url) > 2048 || 1 !== preg_match('#^https?://[^\s\x00-\x1F\x7F]+$#Di', $error->url)) {
        throw new LogicException('A page kept what it must not: '.$error->url);
    }

    if (1 !== preg_match('/^[A-Za-z_$][\w$.]{0,63}$/D', $error->type)) {
        throw new LogicException('A type that is not a JavaScript name: '.$error->type);
    }

    if (1 === preg_match('/[\x00-\x1F\x7F]/', $error->message) || mb_strlen($error->message, 'UTF-8') > 2048 || !mb_check_encoding($error->message, 'UTF-8')) {
        throw new LogicException('A message beyond its bounds.');
    }

    if (count($error->frames) > 5) {
        throw new LogicException('More frames than the bound.');
    }

    foreach ($error->frames as $frame) {
        if (1 !== preg_match('#^https?://[^\s?\#]+$#Di', $frame->file) || 1 === preg_match('/[\x00-\x1F\x7F]/', $frame->function ?? '')) {
            throw new LogicException('A frame kept what it must not: '.$frame->file);
        }
    }
});
$config->setMaxLen(4096);
