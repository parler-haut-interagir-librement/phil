<?php

declare(strict_types=1);

/*
 * The reader on messages nobody wrote: whatever it reads, it returns a part and never throws nor warns; the parts it
 * reads stay within the limits, every text it gives is valid UTF-8, and no file name it proposes holds a directory.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\MimeReader\Limits;
use Phil\MimeReader\MimeReader;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$limits = new Limits(maxBytes: 8192, maxDepth: 6, maxParts: 40, maxHeaders: 50, maxParameters: 8);
$reader = new MimeReader($limits);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader, $limits): void {
    $message = $reader->read($input);
    $count = 0;

    foreach ($message->walk(true) as $part) {
        ++$count;
        $texts = [$part->text(), (string) $part->filename(), (string) $part->headers->decoded('Subject')];

        foreach ($part->headers->mailboxes('To') as $mailbox) {
            $texts[] = (string) $mailbox->name;
        }

        foreach ($texts as $text) {
            if (!mb_check_encoding($text, 'UTF-8')) {
                throw new LogicException('A text is not valid UTF-8.');
            }
        }

        if (str_contains((string) $part->filename(), '/') || str_contains((string) $part->filename(), '\\')) {
            throw new LogicException('A file name holds a directory.');
        }

        $part->fieldGroups();
        $part->content();
    }

    if ($count > $limits->maxParts) {
        throw new LogicException('More parts were read than the limit allows.');
    }
});
$config->setMaxLen(8192);
