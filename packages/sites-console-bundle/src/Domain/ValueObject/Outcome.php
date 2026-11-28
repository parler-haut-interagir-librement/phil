<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\ValueObject;

use Phil\SitesConsoleBundle\Domain\Document\Fields;
use Phil\SitesConsoleBundle\Domain\Document\Json;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;

/**
 * What an agent answers to `configuration` and `update`: whether it worked, and a short message for the report.
 */
final readonly class Outcome
{
    public const int MESSAGE_MAX_LENGTH = 500;

    public string $message;

    private function __construct(
        public bool $succeeded,
        public bool $replayed,
        string $message,
    ) {
        $this->message = mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $message)), 0, self::MESSAGE_MAX_LENGTH);
    }

    public static function succeeded(string $message = ''): self
    {
        return new self(true, false, $message);
    }

    public static function failed(string $message): self
    {
        return new self(false, false, $message);
    }

    public function replay(): self
    {
        return new self($this->succeeded, true, $this->message);
    }

    /**
     * @throws MalformedDocument
     */
    public static function fromJson(string $json): self
    {
        $object = Json::decodeObject($json);
        $outcome = Fields::string($object, 'outcome', 10);

        if (!\in_array($outcome, ['succeeded', 'failed'], true) || !\is_bool($object['replayed'] ?? false)) {
            throw MalformedDocument::because('"outcome" is succeeded or failed, and "replayed" a boolean.');
        }

        return new self('succeeded' === $outcome, $object['replayed'] ?? false, Fields::optionalString($object, 'message', self::MESSAGE_MAX_LENGTH));
    }

    public function toJson(): string
    {
        return Json::encode(['outcome' => $this->succeeded ? 'succeeded' : 'failed', 'replayed' => $this->replayed, 'message' => $this->message]);
    }
}
