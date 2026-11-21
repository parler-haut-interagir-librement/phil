<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\CreationDisclosure;

use Override;
use Phil\CreationDisclosure\DigitalSourceType;
use Phil\MediaBundle\Application\Contract\DeclaredSourceType;
use Phil\MediaBundle\Domain\Model\Media;

/**
 * The digital source type a medium declared with phil/creation-disclosure, by its address in the IPTC vocabulary. A
 * retired type is given as its replacement; a code the vocabulary does not know, read from a column changed by hand,
 * is no declaration.
 */
final readonly class CreationDisclosureSourceType implements DeclaredSourceType
{
    #[Override]
    public function uriOf(Media $media): ?string
    {
        $code = $media->digitalSourceType();

        return null === $code ? null : DigitalSourceType::tryFrom($code)?->current()->uri();
    }
}
