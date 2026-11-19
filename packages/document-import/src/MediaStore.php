<?php

declare(strict_types=1);

namespace Phil\DocumentImport;

/**
 * Where the images of an imported document go: the application keeps their bytes — on a disk, in a media library —
 * and answers the address the image block will show.
 */
interface MediaStore
{
    /**
     * The address of the image once kept: absolute with `http` or `https`, or relative.
     */
    public function store(Media $media): string;
}
