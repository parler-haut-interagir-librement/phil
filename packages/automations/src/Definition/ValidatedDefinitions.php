<?php

declare(strict_types=1);

namespace Phil\Automations\Definition;

use Override;
use Phil\Automations\Contract\Definitions;
use Phil\Automations\Exception\InvalidDefinition;

/**
 * Definitions that refuse to publish a definition the validator does not accept: an unknown trigger or action, an
 * option the description does not take, a condition in an unknown language or one that does not read. A condition in
 * the language `expression` is checked by the evaluator that runs it, with its parser and the variables `payload`,
 * `outputs` and `subject`, so that a definition saved from a builder is refused when it is saved, not when its run
 * reaches the step.
 *
 * ```php
 * $definitions = new ValidatedDefinitions(new DbalDefinitions($connection), new DefinitionValidator($catalogue));
 * ```
 */
final readonly class ValidatedDefinitions implements Definitions
{
    public function __construct(
        private Definitions $definitions,
        private DefinitionValidator $validator,
    ) {
    }

    #[Override]
    public function enabledFor(string $triggerType): iterable
    {
        return $this->definitions->enabledFor($triggerType);
    }

    #[Override]
    public function get(AutomationId $id, int $version): ?AutomationDefinition
    {
        return $this->definitions->get($id, $version);
    }

    /**
     * @throws InvalidDefinition with every problem of the definition, which is then not kept
     */
    #[Override]
    public function publish(AutomationDefinition $definition, bool $enabled = true): void
    {
        $this->validator->validate($definition);
        $this->definitions->publish($definition, $enabled);
    }

    #[Override]
    public function disable(AutomationId $id): void
    {
        $this->definitions->disable($id);
    }
}
