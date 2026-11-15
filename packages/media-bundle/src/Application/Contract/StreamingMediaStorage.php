<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Contract;

use Phil\MediaBundle\Application\MediaStorage;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;

/**
 * A storage that also reads a file as a stream, without holding it whole in memory. Optional: the library reads a
 * storage that does not implement it whole, then hands the contents over as a stream.
 */
interface StreamingMediaStorage extends MediaStorage
{
    /**
     * The file as a readable stream positioned at its start, or `null` when there is none; the caller closes it.
     *
     * @return resource|null
     *
     * @throws InvalidMedia when the key is refused or the storage fails
     */
    public function readStream(string $key): mixed;
}
