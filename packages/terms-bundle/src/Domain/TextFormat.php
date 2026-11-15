<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Domain;

/**
 * How the text of a version is written: plain text, whose paragraphs are separated by a blank line, or a document in
 * UJO (Universal JSON Output), read by `phil/ujo`.
 */
enum TextFormat: string
{
    case Text = 'text';

    case Ujo = 'ujo';
}
