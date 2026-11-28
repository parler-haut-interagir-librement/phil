<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\ValueObject;

use Phil\SitesConsoleBundle\Domain\Document\Fields;
use Phil\SitesConsoleBundle\Domain\Document\Json;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;

/**
 * What the console posts to `configuration`: the push's identifier, which makes it idempotent, and the settings.
 */
final readonly class ConfigurationRequest
{
    public function __construct(
        public string $id,
        public Settings $settings,
    ) {
    }

    /**
     * @throws MalformedDocument
     */
    public static function fromJson(string $json, int $maxBytes = Json::MAX_BYTES): self
    {
        $object = Json::decodeObject($json, $maxBytes);

        try {
            return new self(Fields::string($object, 'id', 64), Settings::of($object['settings'] ?? null));
        } catch (InvalidValue $invalidValue) {
            throw MalformedDocument::because($invalidValue->getMessage());
        }
    }

    public function toJson(): string
    {
        return Json::encode(['id' => $this->id, 'settings' => $this->settings->values]);
    }
}
