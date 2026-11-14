<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Attribute;

use Attribute;

/**
 * Declares a class implementing `SettingMigration` as a migration of the stored values: its version, recorded once
 * applied, and where it goes among the others. Migrations run in the order of their declaration, unless they ask to
 * come before or after other versions.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsSettingMigration
{
    /**
     * @param string       $version letters, digits, `.`, `_` and `-`, at most 190 characters, such as `2026-11-12.signature`
     * @param list<string> $before  the versions it runs before
     * @param list<string> $after   the versions it runs after
     */
    public function __construct(
        public string $version,
        public array $before = [],
        public array $after = [],
    ) {
    }
}
