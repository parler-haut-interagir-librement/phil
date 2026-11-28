<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\Status;

/**
 * How the body of a template is written: HTML with the email components, or Markdown, turned into HTML after the
 * variables are put in, the components kept as they are drawn.
 */
enum BodyFormat: string
{
    case Html = 'html';
    case Markdown = 'markdown';
}
