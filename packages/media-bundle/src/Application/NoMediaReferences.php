<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application;

use Override;
use Phil\MediaBundle\Domain\ValueObject\MediaId;

/**
 * No record shows a medium: the library of an application without `phil_media` columns deletes freely.
 */
final readonly class NoMediaReferences implements MediaReferences
{
    #[Override]
    public function of(MediaId $id): array
    {
        return [];
    }

    #[Override]
    public function detach(MediaId $id, MediaReference $reference): void
    {
    }
}
