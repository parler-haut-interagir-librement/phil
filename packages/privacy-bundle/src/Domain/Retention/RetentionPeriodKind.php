<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Retention;

enum RetentionPeriodKind: string
{
    case Fixed = 'fixed';
    case UntilEvent = 'until_event';
    case Indefinite = 'indefinite';
    case NotYetFixed = 'not_yet_fixed';
}
