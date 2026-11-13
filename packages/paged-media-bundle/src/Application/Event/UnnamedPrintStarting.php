<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application\Event;

/**
 * Dispatched before a print that names no work starts, or before it is queued: a listener that requires every print to
 * name its work with a `PrintSubject` throws, and nothing is rendered, sent or kept.
 */
final readonly class UnnamedPrintStarting
{
}
