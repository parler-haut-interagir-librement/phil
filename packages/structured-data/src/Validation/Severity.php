<?php

declare(strict_types=1);

namespace Phil\StructuredData\Validation;

/**
 * How much an issue weighs: an error keeps a node from being read as its type, a warning leaves it poorer than it
 * could be.
 */
enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
