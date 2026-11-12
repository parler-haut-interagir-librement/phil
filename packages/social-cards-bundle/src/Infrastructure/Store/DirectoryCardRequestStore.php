<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Infrastructure\Store;

use JsonException;
use Phil\SocialCards\Card\Fingerprint;
use Phil\SocialCardsBundle\Application\CardRequest;
use Phil\SocialCardsBundle\Application\Contract\CardRequestStore;
use RuntimeException;

/**
 * The requests of the cards as JSON files beside the cards, `<directory>/<first two digits>/<fingerprint>.json`, each
 * written under a temporary name and renamed into place.
 */
final readonly class DirectoryCardRequestStore implements CardRequestStore
{
    public function __construct(
        private string $directory,
    ) {
    }

    public function save(string $fingerprint, CardRequest $request): bool
    {
        $path = $this->path($fingerprint);

        if (is_file($path)) {
            return false;
        }

        $directory = \dirname($path);
        @mkdir($directory, recursive: true);
        $temporary = \sprintf('%s.%08x.tmp', $path, random_int(0, 0xFFFFFFFF));

        if (false === @file_put_contents($temporary, json_encode($request->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE)) || !@rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException(\sprintf('The request of the card "%s" cannot be written in "%s".', $fingerprint, $directory));
        }

        return true;
    }

    public function find(string $fingerprint): ?CardRequest
    {
        $path = $this->path($fingerprint);
        $json = is_file($path) ? @file_get_contents($path) : false;

        if (false === $json) {
            return null;
        }

        try {
            $values = json_decode($json, true, 4, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return \is_array($values) ? CardRequest::fromArray($values) : null;
    }

    private function path(string $fingerprint): string
    {
        Fingerprint::check($fingerprint);

        return $this->directory.'/'.substr($fingerprint, 0, 2).'/'.$fingerprint.'.json';
    }
}
