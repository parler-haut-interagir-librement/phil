<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Cmi5;

/**
 * An assignable unit of a cmi5 course structure: its IRI, its title, its launch URL — relative to the package or
 * absolute —, what satisfies it, its mastery score, how it asks to be launched, and what it passes on to the content.
 */
final readonly class Cmi5Au
{
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public MoveOn $moveOn = MoveOn::NotApplicable,
        public ?float $masteryScore = null,
        public LaunchMethod $launchMethod = LaunchMethod::AnyWindow,
        public string $description = '',
        public ?string $launchParameters = null,
        public ?string $entitlementKey = null,
        public ?string $activityType = null,
    ) {
    }
}
