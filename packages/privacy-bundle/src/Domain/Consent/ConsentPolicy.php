<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Consent;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * What a site asks consent for: its categories, and the version of the text the person reads.
 *
 * **The version is part of the consent.** A person agreed to a text; when the text changes, the application changes
 * the version, every earlier choice stops counting, and the person is asked again.
 */
final readonly class ConsentPolicy
{
    public const string VERSION_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/D';

    /**
     * @var array<string, ConsentCategory> by name, in the order of the declaration
     */
    public array $categories;

    /**
     * @param iterable<ConsentCategory> $categories
     */
    public function __construct(
        public string $version,
        iterable $categories,
    ) {
        if (1 !== preg_match(self::VERSION_PATTERN, $version)) {
            throw InvalidPrivacyValue::because(\sprintf('"%s" is not a version of the consent text: letters, digits, ".", "_" and "-", at most 32 characters.', $version));
        }

        $byName = [];

        foreach ($categories as $category) {
            if (isset($byName[$category->name])) {
                throw InvalidPrivacyValue::because(\sprintf('The consent category "%s" is declared twice.', $category->name));
            }

            $byName[$category->name] = $category;
        }

        $this->categories = $byName;
    }

    /**
     * The names a person is asked about, in the order of the declaration.
     *
     * @return list<string>
     */
    public function optional(): array
    {
        return array_values(array_map(
            static fn (ConsentCategory $category): string => $category->name,
            array_filter($this->categories, static fn (ConsentCategory $category): bool => !$category->necessary),
        ));
    }

    public function isNecessary(string $name): bool
    {
        return $this->categories[$name]->necessary ?? false;
    }

    /**
     * What can be granted of the names a form sent: the optional categories, in the order of the declaration.
     *
     * @param list<string> $names
     *
     * @return list<string>
     */
    public function retain(array $names): array
    {
        return array_values(array_intersect($this->optional(), $names));
    }
}
