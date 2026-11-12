<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application;

/**
 * The headers of one response: those of the configuration, those a page asked for, and whether they need the endpoint
 * of the reports.
 */
final readonly class ResolvedHeaders
{
    /**
     * @param array<string, string>  $defaults written only when the response does not carry them
     * @param array<string, ?string> $forced   named by an override: written over the response's own, or removed when null
     * @param bool                   $reports  whether a header names the endpoint of the reports
     */
    public function __construct(
        public array $defaults,
        public array $forced,
        public bool $reports,
    ) {
    }
}
