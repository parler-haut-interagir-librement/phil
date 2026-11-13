<?php

declare(strict_types=1);

namespace Phil\ErrorTracking;

/**
 * What one event was to its group: the first of a new group, the regression of a resolved group, or one more.
 */
enum Occurrence: string
{
    case First = 'first';
    case Regression = 'regression';
    case Repeat = 'repeat';

    /**
     * Whether the occurrence calls for an alert: a new group, or a regression.
     */
    public function alerts(): bool
    {
        return self::Repeat !== $this;
    }
}
