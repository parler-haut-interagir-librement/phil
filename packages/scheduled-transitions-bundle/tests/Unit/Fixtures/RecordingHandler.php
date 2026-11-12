<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Tests\Unit\Fixtures;

use Phil\Resource\ResourceReference;
use Phil\ScheduledTransitionsBundle\Application\TransitionHandler;
use RuntimeException;

/**
 * Applies the transitions of pages, writes them down, and fails those it is told to.
 */
final class RecordingHandler implements TransitionHandler
{
    /** @var list<string> */
    public array $applied = [];

    /** @var list<string> */
    public array $failing = [];

    /** @var (callable(string): void)|null what happens while a transition is applied, before it is written down */
    public $during;

    public function supports(ResourceReference $resource, string $transition): bool
    {
        return 'page' === $resource->key;
    }

    public function apply(ResourceReference $resource, string $transition): void
    {
        if (\in_array($resource->id, $this->failing, true)) {
            throw new RuntimeException('The page '.$resource->id.' is locked.');
        }

        if (null !== $this->during) {
            ($this->during)($transition.' '.$resource->toString());
        }

        $this->applied[] = $transition.' '.$resource->toString();
    }
}
