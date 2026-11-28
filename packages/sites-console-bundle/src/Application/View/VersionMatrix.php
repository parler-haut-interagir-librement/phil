<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\View;

use Phil\SitesConsoleBundle\Domain\Version\Packages;

/**
 * The watched packages against the sites: the version each site runs, and the newest one among them.
 */
final readonly class VersionMatrix
{
    /**
     * @param list<string>                         $packages sorted by name
     * @param list<string>                         $sites    the site identifiers, sorted
     * @param array<string, array<string, string>> $versions the versions by site, then by package
     * @param array<string, string>                $newest   the newest version of each package
     */
    public function __construct(
        public array $packages,
        public array $sites,
        public array $versions,
        public array $newest,
    ) {
    }

    public function version(string $site, string $package): ?string
    {
        return $this->versions[$site][$package] ?? null;
    }

    /**
     * Whether the site runs an older version of the package than the newest one.
     */
    public function isBehind(string $site, string $package): bool
    {
        $version = $this->version($site, $package);
        $newest = $this->newest[$package] ?? null;

        return null !== $version && null !== $newest && (Packages::compare($version, $newest) ?? 0) < 0;
    }
}
