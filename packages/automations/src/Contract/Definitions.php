<?php

declare(strict_types=1);

namespace Phil\Automations\Contract;

use Phil\Automations\Definition\AutomationDefinition;
use Phil\Automations\Definition\AutomationId;

/**
 * Where the definitions are kept, every version of each: the latest is published, the earlier ones stay for the runs
 * that started with them.
 */
interface Definitions
{
    /**
     * The published, enabled definitions that start on a trigger type.
     *
     * @return iterable<AutomationDefinition>
     */
    public function enabledFor(string $triggerType): iterable;

    /**
     * One version of a definition; `null` when it is not kept.
     */
    public function get(AutomationId $id, int $version): ?AutomationDefinition;

    /**
     * Keeps a new version and makes it the published one, enabled or not.
     */
    public function publish(AutomationDefinition $definition, bool $enabled = true): void;

    /**
     * Stops the automation from starting new runs; the runs under way go on.
     */
    public function disable(AutomationId $id): void;
}
