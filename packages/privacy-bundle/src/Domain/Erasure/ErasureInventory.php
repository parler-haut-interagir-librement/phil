<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Erasure;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The modules that hold personal data and erase it, as their erasers declare them, and the version of that list.
 *
 * The version is a fingerprint of the sorted module names and of whether each erases in place or through an external
 * service: an erasure request keeps the list and the version it was opened with, so that a module added later never
 * changes what an open request promised, and a module removed later is seen instead of skipped.
 */
final readonly class ErasureInventory
{
    /** The shape of a version: 16 lowercase hexadecimal digits. */
    public const string VERSION_PATTERN = '/^[0-9a-f]{16}$/D';

    /**
     * @param array<string, bool> $modules whether each module erases through an external service, by name, sorted
     */
    private function __construct(
        public array $modules,
        public string $version,
    ) {
    }

    /**
     * @param iterable<ModuleName> $local    the modules that erase in place
     * @param iterable<ModuleName> $external the modules that erase through an external service, acknowledged later
     *
     * @throws InvalidPrivacyValue when a module is declared twice
     */
    public static function of(iterable $local, iterable $external = []): self
    {
        $modules = [];

        foreach ([[$local, false], [$external, true]] as [$names, $isExternal]) {
            foreach ($names as $name) {
                if (isset($modules[$name->value])) {
                    throw InvalidPrivacyValue::because(\sprintf('The module "%s" is declared twice in the erasure inventory.', $name->value));
                }

                $modules[$name->value] = $isExternal;
            }
        }

        ksort($modules, \SORT_STRING);
        $lines = array_map(static fn (string $module, bool $isExternal): string => $module.($isExternal ? ':external' : ':local'), array_keys($modules), $modules);

        return new self($modules, substr(hash('sha256', implode("\n", $lines)), 0, 16));
    }

    public function has(string $module): bool
    {
        return isset($this->modules[$module]);
    }

    public function isExternal(string $module): bool
    {
        return $this->modules[$module] ?? false;
    }

    /**
     * The modules a request erases: every module, or the ones named, each of which must be declared.
     *
     * @param list<string>|null $modules the names, or null for every module
     *
     * @return list<string> sorted
     *
     * @throws InvalidPrivacyValue when a name is not a declared module, or none is left
     */
    public function select(?array $modules = null): array
    {
        if (null === $modules) {
            $selected = array_keys($this->modules);
        } else {
            $selected = [];

            foreach ($modules as $module) {
                if (!isset($this->modules[$module])) {
                    throw InvalidPrivacyValue::because(\sprintf('"%s" is not a module of the erasure inventory.', mb_strimwidth($module, 0, 64, '…')));
                }

                $selected[$module] = $module;
            }

            sort($selected, \SORT_STRING);
        }

        if ([] === $selected) {
            throw InvalidPrivacyValue::because('An erasure request erases at least one module, and none is selected.');
        }

        return $selected;
    }
}
