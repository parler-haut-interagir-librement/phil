<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Automations\Catalogue\ComponentDescription;
use Phil\Automations\Contract\Action;
use Phil\Automations\Contract\RunStore;
use Phil\Automations\Engine\ActionContext;
use Phil\Automations\Engine\Payload;

/**
 * An action that notes when the lease of the run it works for ends.
 */
final class ProbeAction implements Action
{
    /** @var list<?DateTimeImmutable> */
    private array $leases = [];

    public function __construct(
        private readonly RunStore $runs,
    ) {
    }

    /**
     * @return list<?DateTimeImmutable>
     */
    public function leases(): array
    {
        return $this->leases;
    }

    #[Override]
    public function describe(): ComponentDescription
    {
        return new ComponentDescription('test.probe', 'Probe', [], true);
    }

    #[Override]
    public function execute(ActionContext $context): Payload
    {
        $this->leases[] = $this->runs->get($context->runId)?->resumeAt;

        return Payload::of([]);
    }
}
