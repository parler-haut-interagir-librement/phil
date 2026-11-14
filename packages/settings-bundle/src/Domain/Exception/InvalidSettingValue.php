<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A value a setting's rules refuse: of the wrong type, out of its range, too long, not matching its pattern, or not
 * one of its choices. The reason is a translation key of the `phil_settings` domain, and its parameters the values
 * the message names, so that a form shows the refusal in the reader's language.
 */
final class InvalidSettingValue extends InvalidArgumentException implements SettingsException
{
    /**
     * @param array<string, int|string> $parameters
     */
    public function __construct(
        string $message,
        public readonly string $key,
        public readonly string $reason,
        public readonly array $parameters,
    ) {
        parent::__construct($message);
    }

    /**
     * @param string                    $reason     `type`, `range`, `length`, `pattern`, `choice` or `encoding`
     * @param array<string, int|string> $parameters
     */
    public static function of(string $key, string $reason, string $message, array $parameters = []): self
    {
        return new self(\sprintf('The value of the setting "%s" is refused: %s', $key, $message), $key, 'phil_settings.error.'.$reason, $parameters);
    }
}
