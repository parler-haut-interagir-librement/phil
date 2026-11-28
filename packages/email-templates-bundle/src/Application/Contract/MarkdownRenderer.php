<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Contract;

/**
 * Turns the Markdown body of a template, its variables already put in, into HTML that is safe to send.
 */
interface MarkdownRenderer
{
    public function toHtml(string $markdown): string;
}
