<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Twig;

/**
 * What a source of a template renders to, and so how the components draw: HTML, Markdown turned into HTML afterwards,
 * or plain text — the subject, the preheader and the plain-text part.
 */
enum RenderMode
{
    case Html;
    case Markdown;
    case Text;
}
