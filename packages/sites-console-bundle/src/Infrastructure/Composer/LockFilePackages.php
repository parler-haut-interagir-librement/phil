<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Composer;

use JsonException;
use Override;
use Phil\SitesConsoleBundle\Application\Contract\InstalledPackages;
use Phil\SitesConsoleBundle\Domain\Version\Packages;

/**
 * The packages of the site read from its `composer.lock`: those of `packages`, and of `packages-dev` when asked,
 * each name and version checked; a file missing, too large or malformed gives no package. The file is read on each
 * call, so a deployment shows at once and nothing is kept between requests.
 */
final readonly class LockFilePackages implements InstalledPackages
{
    /** The largest lock file read. */
    public const int MAX_BYTES = 8_388_608;

    public function __construct(
        private string $path,
        private bool $includeDev = false,
    ) {
    }

    #[Override]
    public function versions(): array
    {
        $json = is_file($this->path) && is_readable($this->path) ? file_get_contents($this->path, length: self::MAX_BYTES + 1) : false;

        return false === $json || \strlen($json) > self::MAX_BYTES ? [] : self::read($json, $this->includeDev);
    }

    /**
     * @return array<string, string> the versions by package name, sorted by name
     */
    public static function read(string $json, bool $includeDev = false): array
    {
        try {
            $lock = json_decode($json, true, 64, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (!\is_array($lock)) {
            return [];
        }

        $versions = [];

        foreach ($includeDev ? ['packages', 'packages-dev'] : ['packages'] as $section) {
            foreach (\is_array($lock[$section] ?? null) ? $lock[$section] : [] as $package) {
                $name = \is_array($package) ? ($package['name'] ?? null) : null;
                $version = \is_array($package) ? ($package['version'] ?? null) : null;

                if (\is_string($name) && \is_string($version) && Packages::isName($name) && Packages::isVersion($version)) {
                    $versions[$name] = $version;
                }
            }
        }

        ksort($versions, \SORT_STRING);

        return $versions;
    }
}
