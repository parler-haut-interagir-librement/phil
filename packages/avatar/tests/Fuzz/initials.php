<?php

declare(strict_types=1);

/*
 * The initials of names nobody wrote, and the avatars drawn from them: whatever the name, the initials are valid
 * UTF-8, three grapheme clusters at most, each starting with a letter or a digit; the avatar labelled and initialled by
 * that name is a well-formed SVG document whose label is the name, and that holds no element the generator does not
 * write.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Avatar\AvatarGenerator;
use Phil\Avatar\AvatarOptions;
use Phil\Avatar\Initials;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$generator = new AvatarGenerator();
$allowed = ['svg', 'title', 'defs', 'clipPath', 'circle', 'g', 'rect', 'polygon', 'text'];

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($generator, $allowed): void {
    $initials = Initials::of($input, 3);

    if (!mb_check_encoding($initials->letters, 'UTF-8')) {
        throw new LogicException('The initials are not valid UTF-8.');
    }

    preg_match_all('/\\X/u', $initials->letters, $graphemes);

    if (count($graphemes[0]) > 3) {
        throw new LogicException('The initials hold more than three letters.');
    }

    foreach ($graphemes[0] as $grapheme) {
        if (1 !== preg_match('/^[\\p{L}\\p{N}]/u', $grapheme)) {
            throw new LogicException('A letter of the initials starts with neither a letter nor a digit.');
        }
    }

    $label = mb_check_encoding($input, 'UTF-8') && mb_strlen($input) <= AvatarOptions::MAX_LABEL_LENGTH ? $input : null;
    $svg = $generator->svg('fuzz', new AvatarOptions(name: $input, label: $label));
    $document = new DOMDocument();

    if (!@$document->loadXML($svg)) {
        throw new LogicException('An avatar is not a well-formed SVG document.');
    }

    foreach ($document->getElementsByTagName('*') as $element) {
        if (!in_array($element->nodeName, $allowed, true)) {
            throw new LogicException('An avatar holds an element the generator does not write.');
        }
    }

    $name = $document->documentElement?->getAttribute('aria-label');

    if (null !== $label && '' !== trim($label) && $name !== mb_substr(trim($label), 0, AvatarOptions::MAX_LABEL_LENGTH)) {
        throw new LogicException('The label of an avatar is not its name.');
    }
});
$config->setMaxLen(400);
