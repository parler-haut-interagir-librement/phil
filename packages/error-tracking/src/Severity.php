<?php

declare(strict_types=1);

namespace Phil\ErrorTracking;

/**
 * How grave an event is: a warning worth a look, an error that broke what was asked, or a fatal one that stopped the
 * process or the whole request.
 */
enum Severity: string
{
    case Warning = 'warning';
    case Error = 'error';
    case Fatal = 'fatal';
}
