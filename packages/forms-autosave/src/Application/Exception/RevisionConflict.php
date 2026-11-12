<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Application\Exception;

use Phil\FormsAutosave\Domain\Exception\AutosaveException;
use RuntimeException;

/**
 * A save that names a revision other than the one kept: the draft was saved meanwhile, from another tab or device.
 */
final class RevisionConflict extends RuntimeException implements AutosaveException
{
    /**
     * @param ?int $current the revision kept, or null when the draft is gone
     */
    public function __construct(
        public readonly ?int $current,
    ) {
        parent::__construct(null === $current ? 'The draft changed while it was saved.' : \sprintf('The draft is at revision %d.', $current));
    }
}
