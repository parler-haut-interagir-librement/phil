<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Setting;

use Phil\SettingsBundle\Domain\Exception\InvalidSetting;

/**
 * The order of sections, or of the settings of one section, declared by several packages: each comes in the order of
 * its declaration, unless it asks to be before or after others, and then comes right before the first that waits for
 * it. A name that is not declared is passed over, so that a package can place itself next to another that may not be
 * installed; names that ask to come before one another in a circle are refused.
 */
final class Ordering
{
    /**
     * @param array<string, array{before: list<string>, after: list<string>}> $items by name, in the order of declaration
     *
     * @return list<string> the names, in their order
     *
     * @throws InvalidSetting when the names ask to come before one another in a circle
     */
    public static function sort(array $items): array
    {
        /** @var array<string, list<string>> $earlier the names that must come before each one */
        $earlier = array_fill_keys(array_keys($items), []);

        foreach ($items as $name => $item) {
            foreach ($item['after'] as $other) {
                if (isset($earlier[$other])) {
                    $earlier[$name][] = $other;
                }
            }

            foreach ($item['before'] as $other) {
                if (isset($earlier[$other])) {
                    $earlier[$other][] = $name;
                }
            }
        }

        $sorted = [];

        foreach (array_keys($items) as $name) {
            self::place($name, $earlier, $sorted, []);
        }

        return array_values($sorted);
    }

    /**
     * Places the names that must come before one, then the name itself.
     *
     * @param array<string, list<string>> $earlier
     * @param array<string, string>       $sorted  the names placed, in their order
     * @param array<string, string>       $path    the names being placed, which wait for this one
     */
    private static function place(string $name, array $earlier, array &$sorted, array $path): void
    {
        if (isset($sorted[$name])) {
            return;
        }

        if (isset($path[$name])) {
            throw InvalidSetting::because(\sprintf('"%s" ask to come before one another in a circle.', implode('", "', $path)));
        }

        $path[$name] = $name;

        foreach ($earlier[$name] as $other) {
            self::place($other, $earlier, $sorted, $path);
        }

        $sorted[$name] = $name;
    }
}
