<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Application\Contract;

/**
 * Who the current request is for: the identifier of the signed-in user, the one their settings are stored under, or
 * null for a visitor.
 */
interface CurrentUser
{
    public function id(): ?string;
}
