<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Exception;

use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use RuntimeException;

/**
 * No dimension row exists for what was asked.
 *
 * A requested locale without its own row is not found, even when the shared row exists: serving the shared
 * values alone would publish a page in a language nobody wrote.
 */
final class ContentNotFound extends RuntimeException implements DomainError
{
    public static function forDimension(DimensionKey $dimension): self
    {
        return new self(\sprintf('No content exists for the dimension "%s".', $dimension->toString()));
    }

    public static function alongChain(FallbackChain $chain, Stage $stage, Version $version): self
    {
        return new self(\sprintf(
            'No content exists in any locale of the chain "%s" for the stage "%s" and the version %d.',
            $chain->toString(),
            $stage->value,
            $version->number,
        ));
    }

    /**
     * No locale of the chain has a live row, in whichever version each locale was published.
     */
    public static function liveAlongChain(FallbackChain $chain): self
    {
        return new self(\sprintf('No content is published in any locale of the chain "%s".', $chain->toString()));
    }

    public static function inStage(Stage $stage, ?Locale $locale): self
    {
        return new self(\sprintf('No content exists in the stage "%s" for the locale "%s".', $stage->value, $locale->value ?? '*'));
    }

    public static function forReference(ContentReference $reference): self
    {
        return new self(\sprintf('No content root "%s" exists.', $reference->toString()));
    }

    public static function autosave(ContentReference $reference, string $author): self
    {
        return new self(\sprintf('"%s" has no autosave of "%s".', mb_substr($author, 0, 100), $reference->toString()));
    }

    public static function namedDraft(ContentReference $reference, string $name): self
    {
        return new self(\sprintf('"%s" has no draft named "%s".', $reference->toString(), mb_substr($name, 0, 100)));
    }
}
