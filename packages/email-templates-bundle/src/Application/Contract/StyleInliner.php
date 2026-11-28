<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Contract;

/**
 * Moves the rules of the `<style>` elements of a document onto the `style` attribute of the elements they match, for
 * the clients that read no style sheet. The rules a client applies only through a style sheet — the media queries of
 * the dark mode — stay in their `<style>` element.
 */
interface StyleInliner
{
    public function inline(string $html): string;
}
