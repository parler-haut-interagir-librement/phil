<?php

declare(strict_types=1);

namespace Phil\DddBundle\Application\Bus;

/**
 * An intention to change state.
 *
 * A command **returns nothing**. When the caller needs the identifier of what has just been created, it
 * generates it and passes it in the command — see `Identifier::generate()`. That is what lets the handler stay
 * `void` and the bus stay transactional without a return value crossing the transaction boundary.
 *
 * A command may cross a Messenger transport, and the only safe serializer there is the **JSON serializer** —
 * PHP's native default is an object injection surface. It therefore carries only scalars, arrays and strings
 * — no Doctrine objects, no resources, nothing whose reconstruction depends on the state of the container.
 */
interface Command
{
}
