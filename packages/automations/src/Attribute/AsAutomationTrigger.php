<?php

declare(strict_types=1);

namespace Phil\Automations\Attribute;

use Attribute;

/**
 * Marks a service as a trigger of automations, for a container that turns this attribute into the tag `TAG`.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsAutomationTrigger
{
    public const string TAG = 'phil.automations.trigger';
}
