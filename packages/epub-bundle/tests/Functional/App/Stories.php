<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Tests\Functional\App;

use Override;
use Phil\Epub\Book;
use Phil\EpubBundle\Application\AsBookSource;
use Phil\EpubBundle\Application\BookSource;
use Phil\EpubBundle\Infrastructure\Fonts\EpubFonts;
use Phil\EpubBundle\Tests\Unit\Fixture\Books;

/**
 * A book source of the application, registered by its attribute, whose books carry the fonts of the font directory.
 */
#[AsBookSource('story')]
final readonly class Stories implements BookSource
{
    public function __construct(
        private EpubFonts $fonts,
    ) {
    }

    #[Override]
    public function build(string $id): Book
    {
        return $this->fonts->embed(Books::book('Story '.$id), 'Example Sans');
    }
}
