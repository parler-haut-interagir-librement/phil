<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Migration;

/**
 * What a migration step does.
 */
enum StepKind: string
{
    case Rename = 'rename';
    case Convert = 'convert';
    case DefaultChanged = 'default_changed';
    case Remove = 'remove';
}
