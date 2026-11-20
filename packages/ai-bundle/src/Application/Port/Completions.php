<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application\Port;

use Generator;
use Phil\AiBundle\Application\Completion;
use Phil\AiBundle\Application\ModelCall;
use SensitiveParameter;

/**
 * The models: the bundle calls Symfony AI through it, and a test answers in its place.
 */
interface Completions
{
    /**
     * @param string|null $userKey the person's own key, or null for the application's
     */
    public function complete(ModelCall $call, #[SensitiveParameter] ?string $userKey = null): Completion;

    /**
     * The answer in pieces as the model writes them, then the whole completion as the generator's return value.
     *
     * @param string|null $userKey the person's own key, or null for the application's
     *
     * @return Generator<int, string, mixed, Completion>
     */
    public function stream(ModelCall $call, #[SensitiveParameter] ?string $userKey = null): Generator;
}
