<?php

declare(strict_types=1);

namespace Phil\Slides\Domain\Exception;

use RuntimeException;

/**
 * A document or a source that would make a deck past the limits: too many slides, blocks nested too deep, a source
 * too large to read.
 */
final class DeckRefused extends RuntimeException implements SlidesException
{
    public static function tooManySlides(int $limit): self
    {
        return new self(\sprintf('The document makes more than %d slides, the most a deck holds.', $limit));
    }

    public static function tooDeep(int $limit): self
    {
        return new self(\sprintf('The document nests its blocks and their data more than %d levels deep.', $limit));
    }

    public static function sourceTooLarge(int $bytes, int $limit): self
    {
        return new self(\sprintf('The source is %d bytes long, over the %d bytes a deck is read from.', $bytes, $limit));
    }
}
