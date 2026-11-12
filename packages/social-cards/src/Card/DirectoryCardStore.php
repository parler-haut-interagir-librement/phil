<?php

declare(strict_types=1);

namespace Phil\SocialCards\Card;

use Phil\SocialCards\Contract\CardStore;
use Phil\SocialCards\Exception\CardRenderingFailed;

/**
 * Cards kept as files, `<directory>/<first two digits>/<fingerprint>.png`, each written under a temporary name and
 * renamed into place: a reader never sees half a card, and two processes drawing the same card write the same bytes.
 */
final readonly class DirectoryCardStore implements CardStore
{
    public function __construct(
        private string $directory,
    ) {
    }

    public function get(string $fingerprint): ?string
    {
        $path = $this->path($fingerprint);
        $png = is_file($path) ? @file_get_contents($path) : false;

        return false === $png ? null : $png;
    }

    public function put(string $fingerprint, string $png): void
    {
        $path = $this->path($fingerprint);
        $directory = \dirname($path);

        @mkdir($directory, recursive: true);

        if (!is_dir($directory)) {
            throw CardRenderingFailed::because(\sprintf('The directory "%s" cannot be created.', $directory));
        }

        $temporary = \sprintf('%s.%08x.tmp', $path, random_int(0, 0xFFFFFFFF));

        if (false === @file_put_contents($temporary, $png) || !@rename($temporary, $path)) {
            @unlink($temporary);

            throw CardRenderingFailed::because(\sprintf('The card "%s" cannot be written in "%s".', $fingerprint, $directory));
        }
    }

    private function path(string $fingerprint): string
    {
        Fingerprint::check($fingerprint);

        return rtrim($this->directory, '/').'/'.substr($fingerprint, 0, 2).'/'.$fingerprint.'.png';
    }
}
