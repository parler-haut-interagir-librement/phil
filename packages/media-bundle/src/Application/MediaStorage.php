<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application;

/**
 * Where files are kept, by key: `originals/{id}/{version}/{name}`, `formats/{id}/…`.
 */
interface MediaStorage
{
    public function write(string $key, string $contents): void;

    /**
     * The file's contents, or `null` when there is none.
     */
    public function read(string $key): ?string;

    /**
     * Deletes every file whose key starts so.
     */
    public function deletePrefix(string $prefix): void;
}
