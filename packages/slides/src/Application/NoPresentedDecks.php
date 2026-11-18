<?php

declare(strict_types=1);

namespace Phil\Slides\Application;

use Override;
use Phil\Slides\Application\Contract\PresentedDecks;

/**
 * The decks of an application that names none of its own: there is none, and the presenter routes answer 404.
 */
final readonly class NoPresentedDecks implements PresentedDecks
{
    #[Override]
    public function find(string $id): ?Presentation
    {
        return null;
    }
}
