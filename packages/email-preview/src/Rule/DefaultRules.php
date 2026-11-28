<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Rule;

use Phil\EmailPreview\Contract\Rule;

/**
 * The rules the checker runs when it is given none, in the order of its report.
 */
final class DefaultRules
{
    private function __construct()
    {
    }

    /**
     * @return list<Rule>
     */
    public static function all(): array
    {
        return [
            new TextPart(),
            new OneClickUnsubscribe(),
            new TrackingPixel(),
            new NoStyleElement(),
            new MaxWidth(),
            new SubjectLength(),
        ];
    }
}
