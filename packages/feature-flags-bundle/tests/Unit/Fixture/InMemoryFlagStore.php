<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\FeatureFlagsBundle\Application\Toggle\WritableFlagStore;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;

final class InMemoryFlagStore implements WritableFlagStore
{
    public int $writes = 0;

    /**
     * Runs between the read and the write (a compare-and-set or an insert), as a concurrent toggle would.
     *
     * @var (Closure(self): void)|null
     */
    public ?Closure $beforeWrite = null;

    /**
     * @param array<string, bool> $flags
     */
    public function __construct(
        public array $flags = [],
        private readonly CallJournal $journal = new CallJournal(),
    ) {
    }

    #[Override]
    public function isActive(FlagName $flag): ?bool
    {
        $this->journal->add('read');

        return $this->flags[$flag->value] ?? null;
    }

    #[Override]
    public function compareAndSet(FlagName $flag, bool $expected, bool $active): bool
    {
        if ($this->beforeWrite instanceof Closure) {
            ($this->beforeWrite)($this);
        }

        $this->journal->add('write');

        if (($this->flags[$flag->value] ?? null) !== $expected) {
            return false;
        }

        ++$this->writes;
        $this->flags[$flag->value] = $active;

        return true;
    }

    #[Override]
    public function insert(FlagName $flag, bool $active): bool
    {
        if ($this->beforeWrite instanceof Closure) {
            ($this->beforeWrite)($this);
        }

        $this->journal->add('insert');

        if (\array_key_exists($flag->value, $this->flags)) {
            return false;
        }

        ++$this->writes;
        $this->flags[$flag->value] = $active;

        return true;
    }
}
