<?php

declare(strict_types=1);

namespace Phil\Slides\Tests\Functional\App;

use Override;
use Phil\Slides\Application\Contract\PresentedDecks;
use Phil\Slides\Application\DeckReader;
use Phil\Slides\Application\Presentation;

/**
 * The decks of the test application: one talk, `talk-7`, read from Markdown, with its audience page and twenty minutes.
 */
final readonly class TalkDecks implements PresentedDecks
{
    public const string SOURCE = <<<'MD'
        ---
        title: La présentation
        ---

        # Bienvenue

        ::: step
        Un point.
        :::

        ::: notes
        Remercier les **bénévoles**.
        :::

        ---

        ## Suite

        Fin.
        MD;

    public function __construct(private DeckReader $reader)
    {
    }

    #[Override]
    public function find(string $id): ?Presentation
    {
        return 'talk-7' === $id ? new Presentation($this->reader->fromMarkup(self::SOURCE)->withId($id), '/talks/7', 20) : null;
    }
}
