<?php

declare(strict_types=1);

namespace Phil\BounceParser\Rule;

/**
 * The messages a rule is tried on.
 */
enum RuleScope: string
{
    /** Only the messages the parser did not recognise. */
    case Unrecognised = 'unrecognised';
    /** Every message, before what the parser decided: to correct a server whose codes say otherwise than it means. */
    case Always = 'always';
}
