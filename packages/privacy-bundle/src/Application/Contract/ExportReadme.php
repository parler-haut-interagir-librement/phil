<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use DateTimeImmutable;

/**
 * The `README.txt` at the root of an export: what the archive is, when it was made, and what each file holds, written
 * for the person who opens it rather than for a program.
 */
interface ExportReadme
{
    /**
     * @param array<string, int> $modules the number of top-level entries of each module's file, by module name
     */
    public function render(array $modules, DateTimeImmutable $madeAt): string;
}
