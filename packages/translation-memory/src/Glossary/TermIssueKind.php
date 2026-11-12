<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Glossary;

/**
 * What is wrong with a target against the glossary: a term of the source whose translation is missing — or found
 * fewer times than the term —, or a refused translation used.
 */
enum TermIssueKind: string
{
    case Missing = 'missing';
    case Forbidden = 'forbidden';
}
