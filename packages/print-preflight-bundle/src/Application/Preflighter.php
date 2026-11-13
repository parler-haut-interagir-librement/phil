<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Application;

use Phil\PrintPreflight\Check\Check;
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Preflight;
use Phil\PrintPreflight\Report;

/**
 * Runs the preflight of a document against a profile of the configuration: the built-in checks, then the checks of the
 * application.
 */
final readonly class Preflighter
{
    /**
     * @param iterable<Check> $checks the checks of the application, run after the built-in ones
     */
    public function __construct(
        private PrintProfiles $profiles,
        private iterable $checks = [],
    ) {
    }

    /**
     * @param string|null $profile the profile's name; the default one when null
     */
    public function check(Document $document, ?string $profile = null): Report
    {
        $preflight = new Preflight($this->profiles->get($profile));

        foreach ($this->checks as $check) {
            $preflight = $preflight->withCheck($check);
        }

        return $preflight->check($document);
    }

    public function profiles(): PrintProfiles
    {
        return $this->profiles;
    }
}
