<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Signature;

use Phil\SitesConsoleBundle\Domain\Document\Fields;
use Phil\SitesConsoleBundle\Domain\Document\Json;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;

/**
 * The public keys of the console an agent accepts: the current one, and during a rotation the next one. A request
 * signed by either verifies; a key that is neither is refused.
 */
final readonly class ConsoleKeys
{
    public ?VerifyingKey $next;

    public function __construct(
        public VerifyingKey $current,
        ?VerifyingKey $next = null,
    ) {
        $this->next = $next instanceof VerifyingKey && !$next->equals($current) ? $next : null;
    }

    /**
     * The key that verifies the signature of the message, the current one first; null when neither does.
     */
    public function signer(string $message, string $signature): ?VerifyingKey
    {
        if ($this->current->verifies($message, $signature)) {
            return $this->current;
        }

        return $this->next instanceof VerifyingKey && $this->next->verifies($message, $signature) ? $this->next : null;
    }

    public function isCurrent(VerifyingKey $key): bool
    {
        return $this->current->equals($key);
    }

    public function isNext(VerifyingKey $key): bool
    {
        return $this->next instanceof VerifyingKey && $this->next->equals($key);
    }

    /**
     * The same current key with the given one next, in place of any other next key.
     */
    public function staging(VerifyingKey $key): self
    {
        return new self($this->current, $key);
    }

    /**
     * Without the given key: the next key becomes the current one when the current one is retired; a key not held
     * changes nothing.
     *
     * @throws InvalidValue when the key is the only one held
     */
    public function retiring(VerifyingKey $key): self
    {
        if ($this->isNext($key)) {
            return new self($this->current);
        }

        if (!$this->isCurrent($key)) {
            return $this;
        }

        return new self($this->next ?? throw InvalidValue::because('The only key of the console cannot be retired.'));
    }

    public function toJson(): string
    {
        return Json::encode(['current' => $this->current->toBase64(), 'next' => $this->next?->toBase64()]);
    }

    /**
     * @throws MalformedDocument
     */
    public static function fromJson(string $json): self
    {
        $object = Json::decodeObject($json);

        try {
            $next = Fields::optionalString($object, 'next', 64);

            return new self(VerifyingKey::fromBase64(Fields::string($object, 'current', 64)), '' === $next ? null : VerifyingKey::fromBase64($next));
        } catch (InvalidValue $invalidValue) {
            throw MalformedDocument::because($invalidValue->getMessage());
        }
    }
}
