<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Infrastructure\MediaBundle;

use InvalidArgumentException;
use Override;
use Phil\Audio\AudioFormat;
use Phil\Audio\Peaks;
use Phil\Audio\PeaksReader;
use Phil\AudioBundle\Application\Contract\RenditionStore;
use Phil\MediaBundle\Application\MediaStorage;

/**
 * The renditions and waveforms kept in the storage of phil/media-bundle, under `<prefix>/<medium>/`: `<rendition>.<ext>`,
 * `<rendition>-excerpt.<ext>` and `peaks.json`. An identifier or a rendition name holds letters, digits, `_` and `-`
 * only, so that no key leaves its folder.
 */
final readonly class MediaStorageRenditions implements RenditionStore
{
    public function __construct(
        private MediaStorage $storage,
        private string $prefix = 'audio',
        private PeaksReader $reader = new PeaksReader(),
    ) {
    }

    #[Override]
    public function save(string $mediaId, string $rendition, AudioFormat $format, string $path, bool $excerpt = false): void
    {
        $this->storage->write($this->key($mediaId, $this->name($rendition).($excerpt ? '-excerpt' : '').'.'.$format->extension()), (string) file_get_contents($path));
    }

    #[Override]
    public function savePeaks(string $mediaId, Peaks $peaks): void
    {
        $this->storage->write($this->key($mediaId, 'peaks.json'), $peaks->toJson());
    }

    #[Override]
    public function peaks(string $mediaId): ?Peaks
    {
        $json = $this->storage->read($this->key($mediaId, 'peaks.json'));

        return null === $json ? null : $this->reader->read($json);
    }

    /**
     * The key of a file of a medium.
     */
    public function key(string $mediaId, string $file): string
    {
        return trim($this->prefix, '/').'/'.$this->name($mediaId).'/'.$file;
    }

    private function name(string $name): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $name)) {
            throw new InvalidArgumentException(\sprintf('"%s" holds letters, digits, "_" and "-" only, 64 at most.', $name));
        }

        return $name;
    }
}
