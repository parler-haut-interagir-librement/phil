<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Fixture;

use Override;
use Phil\Automations\Catalogue\ComponentDescription;
use Phil\Automations\Catalogue\OptionSpec;
use Phil\Automations\Catalogue\OptionType;
use Phil\Automations\Contract\CompensableAction;
use Phil\Automations\Engine\ActionContext;
use Phil\Automations\Engine\Payload;
use Throwable;

/**
 * An action that plays a script: for each call, a result or an exception, and that keeps every call and every
 * compensation.
 */
final class ScriptedAction implements CompensableAction
{
    /** @var list<ActionContext> */
    public array $calls = [];

    /** @var list<array{ActionContext, Payload}> */
    public array $compensations = [];

    /**
     * @param list<Payload|Throwable> $script what each call gives, the last one repeated
     */
    public function __construct(
        private readonly string $type = 'test.act',
        private array $script = [],
        private readonly ?Throwable $compensationFailure = null,
    ) {
    }

    #[Override]
    public function describe(): ComponentDescription
    {
        return new ComponentDescription($this->type, 'Act', [new OptionSpec('label', OptionType::Text)], true);
    }

    #[Override]
    public function execute(ActionContext $context): Payload
    {
        $this->calls[] = $context;
        $next = \count($this->script) > 1 ? array_shift($this->script) : ($this->script[0] ?? Payload::of(['done' => $context->stepId]));

        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }

    #[Override]
    public function compensate(ActionContext $context, Payload $result): void
    {
        $this->compensations[] = [$context, $result];

        if ($this->compensationFailure instanceof Throwable) {
            throw $this->compensationFailure;
        }
    }
}
