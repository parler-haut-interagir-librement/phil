<?php

declare(strict_types=1);

namespace Phil\Slides\Infrastructure\Twig;

use Phil\Markup\Reader\Reading;
use Phil\Slides\Application\DeckReader;
use Phil\Slides\Application\SlideRenderer;
use Phil\Slides\Domain\Exception\SlidesException;
use Phil\Slides\Domain\ValueObject\Deck;
use Phil\Slides\Domain\ValueObject\Slide;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\UjoException;
use Twig\Attribute\AsTwigFunction;

/**
 * Decks in templates: `slides_deck()` cuts a UJO document, its JSON or what `phil/markup` read into slides,
 * `slides_markup()` reads a source first; `slides_content()` renders what the audience sees of a slide and
 * `slides_notes()` its speaker's notes, for the presenter view only; `slides_kit()` names a recipe of the kit as the
 * application installed it. The bundle's templates call them.
 */
final readonly class SlidesExtension
{
    private string $kitPrefix;

    public function __construct(
        private DeckReader $reader,
        private SlideRenderer $renderer,
        private string $fragmentClass = 'fragment',
        ?string $kitSubdirectory = '',
    ) {
        $path = trim($kitSubdirectory ?? '', '/');
        $this->kitPrefix = '' === $path ? '' : str_replace('/', ':', $path).':';
    }

    /**
     * @param array<array-key, mixed> $metadata the global directives, for a document or its JSON
     *
     * @throws SlidesException
     * @throws UjoException
     */
    #[AsTwigFunction(name: 'slides_deck')]
    public function deck(Document|Reading|string $document, array $metadata = [], ?string $id = null): Deck
    {
        $deck = $document instanceof Reading ? $this->reader->fromReading($document) : $this->reader->fromDocument($document, $metadata);

        return null === $id ? $deck : $deck->withId($id);
    }

    /**
     * @throws SlidesException
     */
    #[AsTwigFunction(name: 'slides_markup')]
    public function markup(string $source, string $format = 'markdown', ?string $id = null): Deck
    {
        $deck = $this->reader->fromMarkup($source, $format);

        return null === $id ? $deck : $deck->withId($id);
    }

    /**
     * @throws UjoException
     */
    #[AsTwigFunction(name: 'slides_content', isSafe: ['html'])]
    public function content(Slide $slide): string
    {
        return $this->renderer->content($slide);
    }

    /**
     * @throws UjoException
     */
    #[AsTwigFunction(name: 'slides_notes', isSafe: ['html'])]
    public function notes(Slide $slide): string
    {
        return $this->renderer->notes($slide);
    }

    /**
     * The class of the containers that appear one at a time.
     */
    #[AsTwigFunction(name: 'slides_fragment_class')]
    public function fragmentClass(): string
    {
        return $this->fragmentClass;
    }

    /**
     * The name under which a recipe of the kit is installed, the application's components directory holding the
     * recipes in the subdirectory of its `ux_toolkit` configuration: with `ui`, `Button` is `ui:Button`;
     * `{% component (slides_kit('Button')) %}`.
     */
    #[AsTwigFunction(name: 'slides_kit')]
    public function kit(string $recipe): string
    {
        return $this->kitPrefix.$recipe;
    }
}
