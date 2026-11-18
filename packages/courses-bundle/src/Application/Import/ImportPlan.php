<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Import;

use Phil\CoursesBundle\Domain\Exception\PackageRefused;
use Phil\ElearningPackages\PackageFormat;

/**
 * The course a package describes, ready to be created: its title, description and format, its identifier in the
 * package, its modules, the blocks of a cmi5 course structure — each parent before the blocks it holds —, and what the
 * import leaves out or keeps for information only.
 */
final readonly class ImportPlan
{
    /**
     * @param list<PlannedModule> $modules
     * @param list<string>        $warnings
     * @param list<PlannedBlock>  $blocks
     *
     * @throws PackageRefused when the plan holds no activity
     */
    public function __construct(
        public string $title,
        public string $description,
        public PackageFormat $format,
        public string $source,
        public array $modules,
        public array $warnings = [],
        public array $blocks = [],
    ) {
        if ([] === $modules) {
            throw PackageRefused::because('The package describes no activity the import can create.');
        }
    }

    public function activities(): int
    {
        return array_sum(array_map(static fn (PlannedModule $module): int => \count($module->activities), $this->modules));
    }

    /**
     * A title as a course, a module or an activity holds it: one line, 255 characters at most.
     */
    public static function title(string $title, string $fallback): string
    {
        $title = trim((string) preg_replace('/\s+/u', ' ', $title));

        return mb_substr('' === $title ? $fallback : $title, 0, 255);
    }
}
