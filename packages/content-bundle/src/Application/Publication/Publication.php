<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Publication;

use Phil\ContentBundle\Domain\Dimension\DimensionLocales;
use Phil\ContentBundle\Domain\Exception\InvalidDimension;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;

/**
 * A content in one locale, as the subject of the publication workflow (`PublicationWorkflow`): what a schedule
 * publishes or takes offline.
 */
final readonly class Publication
{
    private function __construct(
        public ContentReference $reference,
        public Locale $locale,
    ) {
    }

    /**
     * @throws InvalidDimension when the locale is malformed
     */
    public static function of(ContentReference $reference, Locale|string $locale): self
    {
        return new self($reference, \is_string($locale) ? DimensionLocales::parse($locale) : $locale);
    }
}
