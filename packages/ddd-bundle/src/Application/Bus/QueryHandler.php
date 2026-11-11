<?php

declare(strict_types=1);

namespace Phil\DddBundle\Application\Bus;

/**
 * Autoconfiguration marker: tagged `messenger.message_handler` on the configured query bus
 * (`phil_ddd.query_bus`).
 */
interface QueryHandler
{
}
