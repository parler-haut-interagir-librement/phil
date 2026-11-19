<?php

declare(strict_types=1);

namespace Phil\DocumentImport;

/**
 * The block a paragraph style becomes when a `StyleMap` names it: a paragraph, a heading of a level, a quotation, code,
 * or nothing — its paragraphs left out and reported.
 */
enum StyleRole: string
{
    case Paragraph = 'paragraph';
    case Heading1 = 'heading-1';
    case Heading2 = 'heading-2';
    case Heading3 = 'heading-3';
    case Heading4 = 'heading-4';
    case Heading5 = 'heading-5';
    case Heading6 = 'heading-6';
    case Quote = 'quote';
    case Code = 'code';
    case Omit = 'omit';
}
