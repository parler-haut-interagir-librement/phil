<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Import;

/**
 * An activity of an xAPI package that is launched: the IRI the package names it by, its title, its launch — a file of
 * the package, with its query, or an absolute address —, and the scaled score that passes it, when the package gives
 * one.
 */
final readonly class XapiLaunchable
{
    public function __construct(
        public string $id,
        public string $title,
        public string $launch,
        public ?float $masteryScore = null,
    ) {
    }
}
