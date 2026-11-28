<?php

declare(strict_types=1);

namespace Phil\BulkActions\Selection;

use DateTimeImmutable;
use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Internal\Check;

/**
 * A selection waiting for its confirmation, filed under its token's fingerprint: whose it is, for which action, and
 * until when.
 */
final readonly class PendingSelection
{
    public function __construct(
        public string $fingerprint,
        public string $actor,
        public string $target,
        public string $action,
        public Selection $selection,
        public DateTimeImmutable $expiresAt,
    ) {
        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $fingerprint)) {
            throw InvalidInput::because('fingerprint', 'must be a SHA-256 in hexadecimal');
        }

        Check::identifier('actor', $actor);
        Check::name('target', $target);
        Check::name('action', $action);
    }

    /**
     * Whether the selection can still be previewed or confirmed, by this person, at this moment.
     */
    public function isUsableBy(string $actor, DateTimeImmutable $now): bool
    {
        return hash_equals($this->actor, $actor) && $now < $this->expiresAt;
    }

    /**
     * @return array{fingerprint: string, actor: string, target: string, action: string, selection: array<string, mixed>, expires_at: string}
     */
    public function toArray(): array
    {
        return [
            'fingerprint' => $this->fingerprint,
            'actor' => $this->actor,
            'target' => $this->target,
            'action' => $this->action,
            'selection' => $this->selection->toArray(),
            'expires_at' => $this->expiresAt->format(\DATE_ATOM),
        ];
    }

    /**
     * @param array<array-key, mixed> $data what toArray() gave, read back from storage
     */
    public static function fromArray(array $data): self
    {
        $strings = [];

        foreach (['fingerprint', 'actor', 'target', 'action', 'expires_at'] as $key) {
            $value = $data[$key] ?? null;

            if (!\is_string($value)) {
                throw InvalidInput::because('stored selection', 'must have its '.$key);
            }

            $strings[$key] = $value;
        }

        $selection = $data['selection'] ?? null;
        $expiresAt = DateTimeImmutable::createFromFormat(\DATE_ATOM, $strings['expires_at']);

        if (!\is_array($selection) || false === $expiresAt) {
            throw InvalidInput::because('stored selection', 'must have its items and its expiry');
        }

        return new self(
            $strings['fingerprint'],
            $strings['actor'],
            $strings['target'],
            $strings['action'],
            Selection::fromArray($selection),
            $expiresAt,
        );
    }
}
