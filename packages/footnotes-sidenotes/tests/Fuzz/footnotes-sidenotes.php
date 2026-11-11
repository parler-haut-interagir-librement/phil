<?php

declare(strict_types=1);

/*
 * The resolver and the output of notes on inputs nobody wrote: a document either decodes, resolves and renders in every
 * placement, or fails with an exception of UJO.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\FootnotesSidenotes\Html\NotesRenderer;
use Phil\FootnotesSidenotes\NoteResolver;
use Phil\FootnotesSidenotes\Placement;
use Phil\FootnotesSidenotes\Text\NotesPlainText;
use Phil\Ujo\Exception\UjoException;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Ujo;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$ujo = new Ujo();
$resolver = new NoteResolver();
$renderer = new NotesRenderer(HtmlRenderer::default());
$text = new NotesPlainText();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($ujo, $resolver, $renderer, $text): void {
    try {
        $document = $ujo->decode($input);
    } catch (UjoException) {
        return;
    }

    $resolution = $resolver->resolve($document);

    foreach (Placement::cases() as $placement) {
        $renderer->render($document, $resolution, $placement, 'fuzz');
    }

    $text->convert($document, $resolution);
});
$config->setMaxLen(8192);
