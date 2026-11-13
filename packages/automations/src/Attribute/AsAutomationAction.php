<?php

declare(strict_types=1);

namespace Phil\Automations\Attribute;

use Attribute;

/**
 * Marks a service as an action of automations, for a container that turns this attribute into the tag `TAG`.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsAutomationAction
{
    public const string TAG = 'phil.automations.action';
}
