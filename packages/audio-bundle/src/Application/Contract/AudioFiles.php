<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Application\Contract;

use Phil\AudioBundle\Application\Exception\AudioNotFound;

/**
 * Where the uploaded audio files are: a job asks for a local copy of one, by its identifier, and gives it back once
 * done, so that a file kept in remote storage reaches ffmpeg as a path.
 */
interface AudioFiles
{
    /**
     * @return string the path of a local copy, the job's to read until it releases it
     *
     * @throws AudioNotFound when no file has this identifier
     */
    public function localCopy(string $mediaId): string;

    /**
     * Gives back a local copy: a temporary one is deleted.
     */
    public function release(string $path): void;
}
