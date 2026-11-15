<?php

declare(strict_types=1);

namespace Phil\Audio\Contract;

use Phil\Audio\Exception\AudioException;
use Phil\Audio\Excerpt;
use Phil\Audio\Rendition;

/**
 * Turns an audio file into one rendition, whole or an excerpt of it.
 */
interface Transcoder
{
    /**
     * @param string $source the path of a local file
     * @param string $target the path of the file written, replaced when it exists
     *
     * @throws AudioException when the file cannot be transcoded, no tool is configured, or a limit is passed
     */
    public function transcode(string $source, string $target, Rendition $rendition, ?Excerpt $excerpt = null): void;
}
