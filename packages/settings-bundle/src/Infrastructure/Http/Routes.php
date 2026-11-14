<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Infrastructure\Http;

/**
 * The names of the bundle's pages and the shape of a section in their URL.
 */
final class Routes
{
    public const string PREFERENCES = 'phil_settings_preferences';

    public const string SITE = 'phil_settings_site';

    public const string SECTION = '[a-z][a-z0-9_]{0,63}';
}
