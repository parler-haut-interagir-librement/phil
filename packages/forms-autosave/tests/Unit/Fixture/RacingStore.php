<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit\Fixture;

use Closure;
use DateTimeImmutable;
use Override;
use Phil\FormsAutosave\Application\Contract\DraftStore;
use Phil\FormsAutosave\Domain\Model\Draft;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;
use Phil\FormsAutosave\Infrastructure\Memory\InMemoryDraftStore;

/**
 * A store in memory where another request writes just before an insertion or a replacement: the closure runs on the
 * inner store first, once.
 */
final class RacingStore implements DraftStore
{
    public readonly InMemoryDraftStore $inner;

    /** @var list<string> */
    public array $deleted = [];

    /**
     * @param ?Closure(InMemoryDraftStore): void $race
     */
    public function __construct(
        private ?Closure $race = null,
    ) {
        $this->inner = new InMemoryDraftStore();
    }

    #[Override]
    public function find(string $owner, DraftKey $key): ?Draft
    {
        return $this->inner->find($owner, $key);
    }

    #[Override]
    public function insert(Draft $draft): bool
    {
        $this->race();

        return $this->inner->insert($draft);
    }

    #[Override]
    public function replace(Draft $read, Draft $next): bool
    {
        $this->race();

        return $this->inner->replace($read, $next);
    }

    #[Override]
    public function delete(string $owner, DraftKey $key): void
    {
        $this->deleted[] = $key->value;
        $this->inner->delete($owner, $key);
    }

    #[Override]
    public function purge(DateTimeImmutable $now): int
    {
        return $this->inner->purge($now);
    }

    private function race(): void
    {
        if ($this->race instanceof Closure) {
            ($this->race)($this->inner);
            $this->race = null;
        }
    }
}
