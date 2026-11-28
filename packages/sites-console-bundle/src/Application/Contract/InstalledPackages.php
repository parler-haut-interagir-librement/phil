<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Contract;

/**
 * The packages a site runs, with their versions.
 */
interface InstalledPackages
{
    /**
     * @return array<string, string> the versions by package name
     */
    public function versions(): array;
}
