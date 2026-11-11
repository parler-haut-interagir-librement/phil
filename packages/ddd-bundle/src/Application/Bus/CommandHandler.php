<?php

declare(strict_types=1);

namespace Phil\DddBundle\Application\Bus;

/**
 * Autoconfiguration marker: `PhilDddBundle` tags everything that implements it `messenger.message_handler`
 * on the configured command bus (`phil_ddd.command_bus`).
 *
 * A command handler has a single `__invoke` method and returns `void`.
 */
interface CommandHandler
{
}
