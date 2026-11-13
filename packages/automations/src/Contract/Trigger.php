<?php

declare(strict_types=1);

namespace Phil\Automations\Contract;

use Phil\Automations\Catalogue\ComponentDescription;
use Phil\Automations\Definition\Options;
use Phil\Automations\Engine\TriggerMatch;

/**
 * Turns an event of the site into the start of runs: a package that dispatches events registers a trigger for each
 * moment an automation may start on — a subscription confirmed, an order paid.
 *
 * A trigger keeps no state between calls.
 */
interface Trigger
{
    public function describe(): ComponentDescription;

    /**
     * The classes of the events this trigger reads, for the listener the application registers.
     *
     * @return list<class-string>
     */
    public function events(): array;

    /**
     * What a run starts with when an event matches a definition's options; `null` when it does not. The match carries
     * identifiers only, never the event's personal data.
     */
    public function match(object $event, Options $options): ?TriggerMatch;
}
