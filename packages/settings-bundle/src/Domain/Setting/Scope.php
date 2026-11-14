<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Setting;

/**
 * Whose a setting is: each user's own, or the site's, one value for everybody.
 */
enum Scope: string
{
    /** Each user changes their own, in their preferences; a visitor has the default, or their cookie's value. */
    case User = 'user';

    /** One value for the whole site, changed in the administration by the users of a role. */
    case Site = 'site';
}
