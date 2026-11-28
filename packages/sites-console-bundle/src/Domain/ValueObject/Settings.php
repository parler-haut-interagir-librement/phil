<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\ValueObject;

use Phil\SitesConsoleBundle\Domain\Document\Fields;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;

/**
 * The common configuration a push sends: keys in lowercase with dots, dashes and underscores, each a text, a
 * number, a boolean or null.
 */
final readonly class Settings
{
    public const int MAX_SETTINGS = 50;

    public const int VALUE_MAX_LENGTH = 1000;

    private const string KEY_PATTERN = '/^[a-z0-9][a-z0-9_.-]{0,99}$/D';

    /**
     * @param array<string, scalar|null> $values sorted by key
     */
    private function __construct(
        public array $values,
    ) {
    }

    /**
     * @throws InvalidValue
     */
    public static function of(mixed $values): self
    {
        try {
            $object = Fields::object($values, 'settings');
        } catch (MalformedDocument) {
            throw InvalidValue::because('The settings are an object of keys and values.');
        }

        if ([] === $object || \count($object) > self::MAX_SETTINGS) {
            throw InvalidValue::because(\sprintf('A push sends 1 to %d settings.', self::MAX_SETTINGS));
        }

        $checked = [];

        foreach ($object as $key => $value) {
            if (!\is_string($key) || 1 !== preg_match(self::KEY_PATTERN, $key)) {
                throw InvalidValue::because(\sprintf('The key "%s" is 1 to 100 lowercase letters, digits, dots, dashes and underscores, not digits alone.', mb_substr((string) $key, 0, 100)));
            }

            if (!(null === $value || \is_bool($value) || \is_int($value) || \is_float($value) || (\is_string($value) && mb_strlen($value) <= self::VALUE_MAX_LENGTH))) {
                throw InvalidValue::because(\sprintf('The value of "%s" is a text of at most %d characters, a number, a boolean or null.', $key, self::VALUE_MAX_LENGTH));
            }

            $checked[$key] = $value;
        }

        ksort($checked, \SORT_STRING);

        return new self($checked);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->values);
    }
}
