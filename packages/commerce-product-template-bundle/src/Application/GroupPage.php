<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Application;

/**
 * What the page of a product group shows, in one locale: its name, a selector per axis with the values some active
 * member has, in the template's order, and the active members, to find the SKU of the values chosen.
 */
final readonly class GroupPage
{
    /**
     * @param list<array{code: string, label: string, values: list<array{code: string, label: string, hex: string|null, photo: string|null}>}> $axes
     * @param list<array{sku: string, values: array<string, string>, language: string|null, mockups: array<string, string>}>                   $members
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $axes,
        public array $members,
    ) {
    }

    /**
     * The SKU of the active member of these values and language, or `null` when the group sells none.
     *
     * @param array<string, string> $values the code of the value chosen on each axis, by axis code
     */
    public function skuFor(array $values, ?string $language = null): ?string
    {
        ksort($values);

        foreach ($this->members as $member) {
            $memberValues = $member['values'];
            ksort($memberValues);

            if ($member['language'] === $language && $memberValues === $values) {
                return $member['sku'];
            }
        }

        return null;
    }
}
