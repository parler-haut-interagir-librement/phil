<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\ValueObject;

use Phil\SitesConsoleBundle\Domain\Document\Fields;
use Phil\SitesConsoleBundle\Domain\Document\Json;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;
use Phil\SitesConsoleBundle\Domain\Signature\VerifyingKey;
use Phil\SitesConsoleBundle\Domain\Status\KeyOperation;

/**
 * What the console posts to `keys`: the step's identifier, which makes it idempotent, the operation and the public
 * key it is about. Only public keys travel.
 */
final readonly class KeyRequest
{
    public const int ID_MAX_LENGTH = 128;

    public function __construct(
        public string $id,
        public KeyOperation $operation,
        public VerifyingKey $key,
    ) {
    }

    /**
     * @throws MalformedDocument
     */
    public static function fromJson(string $json, int $maxBytes = Json::MAX_BYTES): self
    {
        $object = Json::decodeObject($json, $maxBytes);
        $operation = KeyOperation::tryFrom(Fields::string($object, 'operation', 16));

        if (null === $operation) {
            throw MalformedDocument::because('"operation" is stage or retire.');
        }

        try {
            $key = VerifyingKey::fromBase64(Fields::string($object, 'key', 64));
        } catch (InvalidValue $invalidValue) {
            throw MalformedDocument::because($invalidValue->getMessage());
        }

        return new self(Fields::string($object, 'id', self::ID_MAX_LENGTH), $operation, $key);
    }

    public function toJson(): string
    {
        return Json::encode(['id' => $this->id, 'operation' => $this->operation->value, 'key' => $this->key->toBase64()]);
    }
}
