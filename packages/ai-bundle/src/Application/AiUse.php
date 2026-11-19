<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

use Phil\AiBundle\Domain\Journal\Output;

/**
 * One line of the usage register: a kind of task, what it is for, what it asks for, where it runs, and the tools the
 * model may propose. Every use proposes and a person decides: none applies anything by itself.
 */
final readonly class AiUse
{
    /**
     * @param list<string> $tools the names of the tools the model may propose to call
     */
    public function __construct(
        public string $kind,
        public string $purpose,
        public Output $output,
        public string $platform,
        public string $model,
        public array $tools,
    ) {
    }
}
