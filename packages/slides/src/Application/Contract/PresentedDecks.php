<?php

declare(strict_types=1);

namespace Phil\Slides\Application\Contract;

use Phil\Slides\Application\Presentation;

/**
 * The decks the presenter routes of the bundle show, found by the identifier in their address: the application's own
 * talks, courses or reports. Until the application names its own, the routes find none and answer 404.
 */
interface PresentedDecks
{
    /**
     * The deck of an identifier — letters, digits, `_`, `-`, `.` or `:`, at most 128 —, `null` when there is none.
     */
    public function find(string $id): ?Presentation;
}
