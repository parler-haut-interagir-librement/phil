<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Application\Visitor;

use Phil\SettingsBundle\Application\SettingCatalog;
use Phil\SettingsBundle\Domain\Exception\InvalidSettingValue;
use Phil\SettingsBundle\Domain\Setting\SettingType;

/**
 * The cookie of a visitor's preferences: `display.motion=reduce&display.theme=dark`, the settings kept for visitors
 * whose value is not the default, a boolean written `1` or `0`. Keys and choices need no escaping, so the cookie is
 * read without decoding anything; what the reader does not recognise — an unknown key, a setting not kept for
 * visitors, a refused value, a cookie longer than 1 024 bytes or past its 32nd pair — is left out, never an error.
 */
final readonly class PreferenceCookieFormat
{
    public const int MAX_LENGTH = 1024;

    public const int MAX_PAIRS = 32;

    public function __construct(
        private SettingCatalog $catalog,
    ) {
    }

    /**
     * @return array<string, bool|int|string|list<string>> by key: a boolean or a choice
     */
    public function parse(string $cookie): array
    {
        if (\strlen($cookie) > self::MAX_LENGTH) {
            return [];
        }

        $values = [];

        foreach (\array_slice(explode('&', $cookie), 0, self::MAX_PAIRS) as $pair) {
            [$key, $value] = explode('=', $pair, 2) + [1 => ''];
            $parsed = $this->value($key, $value);

            if (null !== $parsed) {
                $values[$key] = $parsed;
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values by key; those not kept for visitors, refused, or at their default are left out
     */
    public function format(array $values): string
    {
        ksort($values, \SORT_STRING);
        $pairs = [];

        foreach ($values as $key => $value) {
            $written = $this->written($key, $value);

            if (null !== $written) {
                $pairs[] = $key.'='.$written;
            }
        }

        return implode('&', $pairs);
    }

    /**
     * @return bool|int|string|list<string>|null
     */
    private function value(string $key, string $value): bool|int|string|array|null
    {
        if (!$this->catalog->has($key) || !$this->catalog->get($key)->anonymous) {
            return null;
        }

        $definition = $this->catalog->get($key);
        $read = SettingType::Boolean === $definition->type ? ['1' => true, '0' => false][$value] ?? null : $value;

        try {
            return $definition->normalize($read);
        } catch (InvalidSettingValue) {
            return null;
        }
    }

    private function written(string $key, mixed $value): ?string
    {
        if (!$this->catalog->has($key) || !$this->catalog->get($key)->anonymous) {
            return null;
        }

        $definition = $this->catalog->get($key);

        try {
            $normalized = $definition->normalize($value);
        } catch (InvalidSettingValue) {
            return null;
        }

        if ($normalized === $definition->default) {
            return null;
        }

        return \is_bool($normalized) ? ($normalized ? '1' : '0') : (\is_string($normalized) ? $normalized : null);
    }
}
