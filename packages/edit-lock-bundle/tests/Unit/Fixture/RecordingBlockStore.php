<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\EditLockBundle\Application\BlockStore;
use Phil\EditLockBundle\Domain\ValueObject\Fence;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\Ujo\Block;

/**
 * Notes each write with the number of its fence; `$during` runs inside each write, before it is noted.
 */
final class RecordingBlockStore implements BlockStore
{
    /** @var list<string> */
    public array $kept = [];

    /** @var list<Fence> */
    public array $fences = [];

    /**
     * @param (Closure(): void)|null $during
     */
    public function __construct(
        public ?Closure $during = null,
    ) {
    }

    #[Override]
    public function save(string $resource, Block $block, string $list, ?string $after, Holder $holder, Fence $fence): void
    {
        $this->run($fence);
        $this->kept[] = 'save '.$resource.' '.$block->id.' '.$block->type.' '.$list.' '.($after ?? '-').' '.$holder->id.' #'.$fence->number;
    }

    #[Override]
    public function remove(string $resource, string $blockId, Holder $holder, Fence $fence): void
    {
        $this->run($fence);
        $this->kept[] = 'remove '.$resource.' '.$blockId.' '.$holder->id.' #'.$fence->number;
    }

    private function run(Fence $fence): void
    {
        $this->fences[] = $fence;

        if ($this->during instanceof Closure) {
            ($this->during)();
        }
    }
}
