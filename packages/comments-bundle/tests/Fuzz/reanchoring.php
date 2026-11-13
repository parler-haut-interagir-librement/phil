<?php

declare(strict_types=1);

/*
 * The anchoring on inputs nobody wrote: the first line is a passage, the rest inline HTML cut into fields at each
 * blank line. Whatever they hold, reading the HTML, finding the passage again and replacing it never throw but for
 * the passages an anchor refuses; a passage found is always the passage itself, at its offset, in its field.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\CommentsBundle\Domain\Anchoring\InlineHtml;
use Phil\CommentsBundle\Domain\Anchoring\Passage;
use Phil\CommentsBundle\Domain\Anchoring\Reanchoring;
use Phil\CommentsBundle\Domain\Exception\InvalidComment;
use Phil\CommentsBundle\Domain\ValueObject\Anchor;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    [$quote, $rest] = array_pad(explode("\n", $input, 2), 2, '');
    $passages = [];

    foreach (explode("\n\n", $rest) as $index => $html) {
        $passages[] = new Passage('b'.$index, 'text', InlineHtml::text($html));
    }

    try {
        $anchor = Anchor::of('b0', 'text', mb_strlen($rest) % 64, $quote, mb_substr($rest, 0, 40), mb_substr($rest, -40));
    } catch (InvalidComment) {
        return;
    }

    $found = Reanchoring::locate($anchor, $passages);

    if (!$found instanceof Anchor) {
        return;
    }

    $field = array_find($passages, static fn (Passage $passage): bool => $passage->block === $found->block) ?? throw new LogicException('Found in a field that was not given.');

    if (mb_substr($field->text, $found->start, $found->end - $found->start) !== $quote) {
        throw new LogicException(sprintf('The passage is not at offset %d of its field.', $found->start));
    }

    $html = explode("\n\n", $rest)[(int) substr((string) $found->block, 1)];

    if (InlineHtml::text(InlineHtml::replace($html, $found->start, $found->end - $found->start, $quote)) !== InlineHtml::text($html) && mb_check_encoding($html, 'UTF-8')) {
        throw new LogicException('Replacing a passage by itself changed the text of its field.');
    }
});
$config->setMaxLen(4096);
