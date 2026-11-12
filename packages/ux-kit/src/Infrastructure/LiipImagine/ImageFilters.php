<?php

declare(strict_types=1);

namespace Phil\UxKit\Infrastructure\LiipImagine;

use InvalidArgumentException;
use Liip\ImagineBundle\Exception\Imagine\Filter\NonExistingFilterException;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;

/**
 * The props of a `Picture` from the filter sets of LiipImagine applied to one image: each set a candidate of a `srcset`
 * of widths, at the address LiipImagine gives it, resized when it is first asked for. The sets that write AVIF or WebP,
 * by their `format`, make the sources; the others, or WebP when every set writes it, the fallback.
 *
 * The width of each set is the one its last resizing filter gives — `thumbnail`, `fixed`, `resize`, `scale`,
 * `downscale` or `relative_resize` with `widen` —, or the one the template gives. The fallback's widest set gives `src`,
 * and the width and height of the image when it crops to an exact size: a `thumbnail` in `outbound` mode, or `fixed`.
 */
final readonly class ImageFilters
{
    /** The types of the formats of the sources, in their order. */
    private const array SOURCES = ['avif' => 'image/avif', 'webp' => 'image/webp'];

    public function __construct(
        private CacheManager $cache,
        private FilterConfiguration $configuration,
    ) {
    }

    /**
     * @param string                          $path    the path of the image, as LiipImagine's loader reads it
     * @param list<string>|array<string, int> $filters the names of the filter sets, or each name with its width
     *
     * @return array{src: string, srcset?: string, sources: list<array{type: string, srcset: string}>, width?: int, height?: int}
     *
     * @throws InvalidArgumentException when no set is given, a set is unknown or its width can be read nowhere
     */
    public function of(string $path, array $filters): array
    {
        if ([] === $filters) {
            throw new InvalidArgumentException(\sprintf('No filter set for "%s": give the names of the sets of its candidates.', $path));
        }

        /** @var array<string, non-empty-list<array{width: int, url: string, config: array<array-key, mixed>}>> $formats */
        $formats = [];

        foreach ($filters as $key => $value) {
            [$format, $candidate] = \is_int($key) ? $this->candidate($path, $value, null) : $this->candidate($path, $key, $value);
            $formats[$format][] = $candidate;
        }

        $others = array_diff_key($formats, self::SOURCES);
        $fallback = [] === $others ? (isset($formats['webp']) ? 'webp' : 'avif') : array_key_first($others);
        $candidates = $this->sorted($formats[$fallback]);
        $widest = array_last($candidates);

        $props = ['src' => $widest['url']];

        if (\count($candidates) > 1) {
            $props['srcset'] = $this->set($candidates);
        }

        $props['sources'] = [];

        foreach (self::SOURCES as $format => $type) {
            if ($format !== $fallback && isset($formats[$format])) {
                $props['sources'][] = ['type' => $type, 'srcset' => $this->set($this->sorted($formats[$format]))];
            }
        }

        return [...$props, ...$this->size($widest['config'])];
    }

    /**
     * The format of one filter set, and its candidate: its width, its address and its configuration.
     *
     * @return array{string, array{width: int, url: string, config: array<array-key, mixed>}}
     *
     * @throws InvalidArgumentException when the set is unknown or its width can be read nowhere
     */
    private function candidate(string $path, mixed $name, mixed $width): array
    {
        if (!\is_string($name) || '' === $name) {
            throw new InvalidArgumentException('A filter set is named by a string.');
        }

        try {
            $config = $this->configuration->get($name);
        } catch (NonExistingFilterException) {
            throw new InvalidArgumentException(\sprintf('LiipImagine has no filter set "%s".', $name));
        }

        $width ??= $this->width($config);

        if (!\is_int($width) || $width < 1) {
            throw new InvalidArgumentException(\sprintf('The width of the filter set "%s" is read from none of its filters: give it, {%s: 480}.', $name, $name));
        }

        $format = \is_string($config['format'] ?? null) && '' !== $config['format'] ? $config['format'] : pathinfo($path, \PATHINFO_EXTENSION);

        return [strtolower($format), ['width' => $width, 'url' => $this->cache->getBrowserPath($path, $name), 'config' => $config]];
    }

    /**
     * @param non-empty-list<array{width: int, url: string, config: array<array-key, mixed>}> $candidates
     *
     * @return non-empty-list<array{width: int, url: string, config: array<array-key, mixed>}>
     */
    private function sorted(array $candidates): array
    {
        usort($candidates, static fn (array $a, array $b): int => $a['width'] <=> $b['width']);

        return $candidates;
    }

    /**
     * The candidates as a `srcset` of widths, or the one address alone.
     *
     * @param non-empty-list<array{width: int, url: string, config: array<array-key, mixed>}> $candidates
     */
    private function set(array $candidates): string
    {
        if (1 === \count($candidates)) {
            return $candidates[0]['url'];
        }

        return implode(', ', array_map(static fn (array $candidate): string => $candidate['url'].' '.$candidate['width'].'w', $candidates));
    }

    /**
     * The width the last resizing filter of a set gives, or null.
     *
     * @param array<array-key, mixed> $config
     */
    private function width(array $config): ?int
    {
        $width = null;

        foreach ($this->filters($config) as $filter => $options) {
            $read = match ($filter) {
                'thumbnail', 'resize' => $this->first($options['size'] ?? null),
                'scale' => $this->first($options['dim'] ?? null),
                'downscale' => $this->first($options['max'] ?? null),
                'fixed' => $options['width'] ?? null,
                'relative_resize' => $options['widen'] ?? null,
                default => null,
            };

            $width = \is_int($read) && $read > 0 ? $read : $width;
        }

        return $width;
    }

    /**
     * The width and height a set crops to exactly: those of a `thumbnail` in `outbound` mode or of `fixed`, when it is
     * the last resizing filter.
     *
     * @param array<array-key, mixed> $config
     *
     * @return array{width?: int, height?: int}
     */
    private function size(array $config): array
    {
        $size = [];

        foreach ($this->filters($config) as $filter => $options) {
            $size = match ($filter) {
                'thumbnail' => 'outbound' === ($options['mode'] ?? null) ? $this->pair($options['size'] ?? null) : [],
                'fixed' => $this->pair([$options['width'] ?? null, $options['height'] ?? null]),
                'resize', 'scale', 'downscale', 'relative_resize' => [],
                default => $size,
            };
        }

        return $size;
    }

    /**
     * The filters of a set whose options are a map, in their order.
     *
     * @param array<array-key, mixed> $config
     *
     * @return array<array-key, array<array-key, mixed>>
     */
    private function filters(array $config): array
    {
        return array_filter(\is_array($config['filters'] ?? null) ? $config['filters'] : [], \is_array(...));
    }

    /**
     * A width and a height, each a positive integer, or nothing.
     *
     * @return array{width?: int, height?: int}
     */
    private function pair(mixed $pair): array
    {
        $width = \is_array($pair) ? ($pair[0] ?? null) : null;
        $height = \is_array($pair) ? ($pair[1] ?? null) : null;

        return \is_int($width) && $width > 0 && \is_int($height) && $height > 0 ? ['width' => $width, 'height' => $height] : [];
    }

    private function first(mixed $pair): mixed
    {
        return \is_array($pair) ? ($pair[0] ?? null) : null;
    }
}
