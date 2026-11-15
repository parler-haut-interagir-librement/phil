<?php

declare(strict_types=1);

namespace Phil\Audio\Contract;

use Phil\Audio\AudioMetadata;
use Phil\Audio\Exception\AudioException;

/**
 * Reads what an audio file says of itself.
 */
interface MetadataReader
{
    /**
     * @param string $path the path of a local file
     *
     * @throws AudioException when the file cannot be read, no tool is configured, or a limit is passed
     */
    public function read(string $path): AudioMetadata;
}
