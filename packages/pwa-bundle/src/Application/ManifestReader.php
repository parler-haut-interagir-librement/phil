<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Application;

use Phil\PwaBundle\Domain\Exception\InvalidManifest;
use Phil\PwaBundle\Domain\Manifest\CacheStrategy;
use Phil\PwaBundle\Domain\Manifest\DisplayMode;
use Phil\PwaBundle\Domain\Manifest\IconPurpose;
use Phil\PwaBundle\Domain\Manifest\Orientation;
use Phil\PwaBundle\Domain\ValueObject\CacheRule;
use Phil\PwaBundle\Domain\ValueObject\Icon;
use Phil\PwaBundle\Domain\ValueObject\Manifest;
use Phil\PwaBundle\Domain\ValueObject\Shortcut;
use Phil\PwaBundle\Domain\ValueObject\WorkerSettings;

/**
 * Reads the manifest and the settings of the service worker from plain data — the configuration, with the members of
 * the manifest in snake case:
 *
 * ```yaml
 * name: Example
 * short_name: Example
 * start_url: /?source=pwa
 * display: fullscreen
 * theme_color: '#1b1b1b'
 * icons:
 *     - { src: images/icon-192.png, sizes: 192x192, type: image/png }
 *     - { src: images/icon-maskable.png, sizes: 512x512, type: image/png, purpose: [maskable] }
 * ```
 *
 * A key the manifest does not have, or a value of another type, is refused with InvalidManifest, which names the
 * member, never the value.
 */
final readonly class ManifestReader
{
    private const array MANIFEST_KEYS = ['name', 'short_name', 'description', 'id', 'start_url', 'scope', 'display', 'display_override', 'orientation', 'theme_color', 'background_color', 'lang', 'dir', 'icons', 'shortcuts', 'categories'];

    /**
     * @param array<mixed> $data
     */
    public function read(array $data): Manifest
    {
        $this->keys($data, self::MANIFEST_KEYS, 'manifest');
        $icons = [];

        foreach ($this->maps($data, 'icons', Manifest::MAX_ICONS) as $index => $icon) {
            $where = \sprintf('icon %d', $index + 1);
            $this->keys($icon, ['src', 'sizes', 'type', 'purpose'], $where);
            $icons[] = new Icon(
                $this->string($icon, 'src', $where),
                $this->optional($icon, 'sizes', $where) ?? 'any',
                $this->optional($icon, 'type', $where),
                array_map(static fn (string $purpose): IconPurpose => IconPurpose::tryFrom($purpose) ?? throw InvalidManifest::because(\sprintf('The purpose of %s is "any", "maskable" or "monochrome".', $where)), $this->strings($icon, 'purpose', $where)),
            );
        }

        $shortcuts = [];

        foreach ($this->maps($data, 'shortcuts', Manifest::MAX_SHORTCUTS) as $index => $shortcut) {
            $where = \sprintf('shortcut %d', $index + 1);
            $this->keys($shortcut, ['name', 'url', 'short_name', 'description'], $where);
            $shortcuts[] = new Shortcut($this->string($shortcut, 'name', $where), $this->string($shortcut, 'url', $where), $this->optional($shortcut, 'short_name', $where), $this->optional($shortcut, 'description', $where));
        }

        $orientation = $this->optional($data, 'orientation', 'manifest');

        return new Manifest(
            $this->string($data, 'name', 'manifest'),
            $this->optional($data, 'short_name', 'manifest'),
            $this->optional($data, 'description', 'manifest'),
            $this->optional($data, 'id', 'manifest') ?? '/',
            $this->optional($data, 'start_url', 'manifest') ?? '/',
            $this->optional($data, 'scope', 'manifest') ?? '/',
            $this->display($this->optional($data, 'display', 'manifest') ?? DisplayMode::Standalone->value),
            array_map($this->display(...), $this->strings($data, 'display_override', 'manifest')),
            null === $orientation ? null : Orientation::tryFrom($orientation) ?? throw InvalidManifest::because('The orientation is one of the orientations of the Screen Orientation API, such as "portrait".'),
            $this->optional($data, 'theme_color', 'manifest'),
            $this->optional($data, 'background_color', 'manifest'),
            $this->optional($data, 'lang', 'manifest'),
            $this->optional($data, 'dir', 'manifest'),
            $icons,
            $shortcuts,
            $this->strings($data, 'categories', 'manifest'),
        );
    }

    /**
     * @param array<mixed> $data
     */
    public function worker(array $data): WorkerSettings
    {
        $this->keys($data, ['cache', 'scope', 'cache_rules', 'exclude', 'offline'], 'service worker');
        $rules = [];

        foreach ($this->maps($data, 'cache_rules', WorkerSettings::MAX_RULES) as $index => $rule) {
            $where = \sprintf('cache rule %d', $index + 1);
            $this->keys($rule, ['prefix', 'strategy'], $where);
            $rules[] = new CacheRule(
                $this->string($rule, 'prefix', $where),
                CacheStrategy::tryFrom($this->string($rule, 'strategy', $where)) ?? throw InvalidManifest::because(\sprintf('The strategy of %s is "network-first", "cache-first" or "network-only".', $where)),
            );
        }

        $offline = $data['offline'] ?? true;

        if (!\is_bool($offline)) {
            throw InvalidManifest::because('The offline setting of the service worker is a boolean.');
        }

        return new WorkerSettings(
            $this->optional($data, 'cache', 'service worker') ?? 'phil-pwa',
            $this->optional($data, 'scope', 'service worker') ?? '/',
            $rules,
            $this->strings($data, 'exclude', 'service worker'),
            $offline,
        );
    }

    private function display(string $value): DisplayMode
    {
        return DisplayMode::tryFrom($value) ?? throw InvalidManifest::because('A display mode is "fullscreen", "standalone", "minimal-ui", "browser", "window-controls-overlay" or "tabbed".');
    }

    /**
     * @param array<mixed> $data
     * @param list<string> $allowed
     */
    private function keys(array $data, array $allowed, string $where): void
    {
        foreach (array_keys($data) as $key) {
            if (!\in_array($key, $allowed, true)) {
                throw InvalidManifest::because(\sprintf('The keys of the %s are %s.', $where, implode(', ', $allowed)));
            }
        }
    }

    /**
     * @param array<mixed> $data
     */
    private function string(array $data, string $key, string $where): string
    {
        return $this->optional($data, $key, $where) ?? throw InvalidManifest::because(\sprintf('The %s of the %s is a string.', $key, $where));
    }

    /**
     * @param array<mixed> $data
     */
    private function optional(array $data, string $key, string $where): ?string
    {
        $value = $data[$key] ?? null;

        if (null !== $value && !\is_string($value)) {
            throw InvalidManifest::because(\sprintf('The %s of the %s is a string.', $key, $where));
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    private function strings(array $data, string $key, string $where): array
    {
        $values = $data[$key] ?? [];

        if (!\is_array($values) || !array_is_list($values) || \count($values) > 20) {
            throw InvalidManifest::because(\sprintf('The %s of the %s are a list of 20 strings at most.', $key, $where));
        }

        foreach ($values as $value) {
            if (!\is_string($value)) {
                throw InvalidManifest::because(\sprintf('The %s of the %s are a list of 20 strings at most.', $key, $where));
            }
        }

        return $values;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<array<mixed>>
     */
    private function maps(array $data, string $key, int $max): array
    {
        $values = $data[$key] ?? [];

        if (!\is_array($values) || !array_is_list($values) || \count($values) > $max) {
            throw InvalidManifest::because(\sprintf('The %s are a list of %d maps at most.', $key, $max));
        }

        foreach ($values as $value) {
            if (!\is_array($value)) {
                throw InvalidManifest::because(\sprintf('The %s are a list of %d maps at most.', $key, $max));
            }
        }

        return $values;
    }
}
