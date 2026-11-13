<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application\Port;

use Phil\PagedMediaBundle\Application\Output;

/**
 * Where the files rendered by a message are kept, under the relative path the message gives. The bundle writes them to
 * a directory; an application keeps them elsewhere — a Flysystem storage, a media library — by aliasing this port to a
 * service of its own.
 */
interface PdfStorage
{
    public function store(string $path, Output $output): void;
}
