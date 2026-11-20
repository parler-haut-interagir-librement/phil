<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

/**
 * What a task tells the model: the instructions of the system, when its template has some, and the request of the
 * user. A prompt goes to the platform and nowhere else; the journal never sees it.
 */
final readonly class Prompt
{
    public function __construct(
        public ?string $system,
        public string $user,
    ) {
    }
}
