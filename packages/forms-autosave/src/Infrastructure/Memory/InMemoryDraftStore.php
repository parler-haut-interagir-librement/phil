<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Infrastructure\Memory;

use DateTimeImmutable;
use Override;
use Phil\FormsAutosave\Application\Contract\DraftStore;
use Phil\FormsAutosave\Domain\Model\Draft;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The drafts in the memory of the process, for tests: emptied when the container is reset between requests.
 */
final class InMemoryDraftStore implements DraftStore, ResetInterface
{
    /** @var array<string, array<string, Draft>> by owner, then by key */
    private array $drafts = [];

    #[Override]
    public function find(string $owner, DraftKey $key): ?Draft
    {
        return $this->drafts[$owner][$key->value] ?? null;
    }

    #[Override]
    public function insert(Draft $draft): bool
    {
        if ($this->find($draft->owner(), $draft->key()) instanceof Draft) {
            return false;
        }

        $this->drafts[$draft->owner()][$draft->key()->value] = $draft;

        return true;
    }

    #[Override]
    public function replace(Draft $read, Draft $next): bool
    {
        if ($this->find($read->owner(), $read->key())?->revision() !== $read->revision()) {
            return false;
        }

        $this->drafts[$read->owner()][$read->key()->value] = $next;

        return true;
    }

    #[Override]
    public function delete(string $owner, DraftKey $key): void
    {
        unset($this->drafts[$owner][$key->value]);
    }

    #[Override]
    public function purge(DateTimeImmutable $now): int
    {
        $purged = 0;

        foreach ($this->drafts as $owner => $drafts) {
            $kept = array_filter($drafts, static fn (Draft $draft): bool => !$draft->hasExpired($now));
            $purged += \count($drafts) - \count($kept);
            $this->drafts[$owner] = $kept;
        }

        return $purged;
    }

    #[Override]
    public function reset(): void
    {
        $this->drafts = [];
    }
}
