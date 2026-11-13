<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Domain\ValueObject;

use JsonException;
use Phil\EmbedBundle\Domain\Enum\MessageType;
use Phil\EmbedBundle\Domain\Exception\InvalidEmbedMessage;

/**
 * A message of the frame protocol, as the page and the frame exchange it by `postMessage`: an object with
 * `protocol: "phil-embed"`, `version: 1` and a `type`; `resize` adds `height`, a whole number of pixels from 0 to
 * 100 000, and `theme` adds `theme`, a theme's name. Nothing else is accepted.
 *
 * This class is the protocol's reference: the bundle's JavaScript reads messages by the same rules, and both are
 * tested against the same fixtures.
 */
final readonly class EmbedMessage
{
    public const string PROTOCOL = 'phil-embed';

    public const int VERSION = 1;

    public const int MAX_HEIGHT = 100_000;

    /** The longest JSON text read, in bytes. */
    public const int MAX_LENGTH = 1024;

    private function __construct(
        public MessageType $type,
        public ?int $height = null,
        public ?string $theme = null,
    ) {
    }

    public static function ready(): self
    {
        return new self(MessageType::Ready);
    }

    /**
     * @throws InvalidEmbedMessage when the height is out of bounds
     */
    public static function resize(int $height): self
    {
        if ($height < 0 || $height > self::MAX_HEIGHT) {
            throw InvalidEmbedMessage::because(\sprintf('A height is 0 to %d pixels, not %d.', self::MAX_HEIGHT, $height));
        }

        return new self(MessageType::Resize, $height);
    }

    /**
     * @throws InvalidEmbedMessage when the name is not one of a theme
     */
    public static function theme(string $theme): self
    {
        if (1 !== preg_match(EmbedClaims::THEME, $theme)) {
            throw InvalidEmbedMessage::because('A theme is a lowercase letter, then up to 31 lowercase letters, digits or "-".');
        }

        return new self(MessageType::Theme, theme: $theme);
    }

    /**
     * @throws InvalidEmbedMessage when the text is not a message of the protocol
     */
    public static function fromJson(string $json): self
    {
        if (\strlen($json) > self::MAX_LENGTH) {
            throw InvalidEmbedMessage::because(\sprintf('A message is %d bytes at most.', self::MAX_LENGTH));
        }

        try {
            $data = json_decode($json, false, 2, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidEmbedMessage::because('A message is a JSON object of scalar values.');
        }

        if (!\is_object($data)) {
            throw InvalidEmbedMessage::because('A message is an object.');
        }

        return self::fromArray(get_object_vars($data));
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidEmbedMessage when the values are not a message of the protocol
     */
    public static function fromArray(array $data): self
    {
        if (self::PROTOCOL !== ($data['protocol'] ?? null) || !self::isNumber($data['version'] ?? null, self::VERSION)) {
            throw InvalidEmbedMessage::because(\sprintf('A message is of the protocol "%s", version %d.', self::PROTOCOL, self::VERSION));
        }

        $type = \is_string($data['type'] ?? null) ? MessageType::tryFrom($data['type']) : null;
        $expected = match ($type) {
            MessageType::Resize => ['protocol', 'version', 'type', 'height'],
            MessageType::Theme => ['protocol', 'version', 'type', 'theme'],
            MessageType::Ready => ['protocol', 'version', 'type'],
            null => throw InvalidEmbedMessage::because('A message is of the type "ready", "resize" or "theme".'),
        };
        $keys = array_keys($data);
        sort($keys);
        sort($expected);

        if ($keys !== $expected) {
            throw InvalidEmbedMessage::because(\sprintf('A "%s" message holds %s and nothing else.', $type->value, implode(', ', $expected)));
        }

        return match ($type) {
            MessageType::Resize => self::resize(self::wholeNumber($data['height'])),
            MessageType::Theme => self::theme(\is_string($data['theme']) ? $data['theme'] : throw InvalidEmbedMessage::because('A theme is a string.')),
            MessageType::Ready => self::ready(),
        };
    }

    /**
     * @return array{protocol: string, version: int, type: string, height?: int, theme?: string}
     */
    public function toArray(): array
    {
        $data = ['protocol' => self::PROTOCOL, 'version' => self::VERSION, 'type' => $this->type->value];

        if (null !== $this->height) {
            $data['height'] = $this->height;
        }

        if (null !== $this->theme) {
            $data['theme'] = $this->theme;
        }

        return $data;
    }

    /**
     * Whether a value is that number, as JavaScript compares numbers: `1` and `1.0` are the same.
     */
    private static function isNumber(mixed $value, int $number): bool
    {
        return $number === $value || (float) $number === $value;
    }

    /**
     * A whole number, written `5` or `5.0` as JavaScript does not tell them apart.
     */
    private static function wholeNumber(mixed $value): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_float($value) && is_finite($value) && floor($value) === $value && abs($value) <= self::MAX_HEIGHT) {
            return (int) $value;
        }

        throw InvalidEmbedMessage::because('A height is a whole number of pixels.');
    }
}
