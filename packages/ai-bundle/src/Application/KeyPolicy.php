<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

/**
 * Whose key a task of a person runs on.
 */
enum KeyPolicy: string
{
    /** Always the application's key; the person's keys are never read. */
    case Application = 'application';

    /** The person's own key when they have one for the platform, the application's otherwise. */
    case PreferUser = 'prefer_user';

    /** Only the person's own key: without one, the task is refused. */
    case RequireUser = 'require_user';
}
