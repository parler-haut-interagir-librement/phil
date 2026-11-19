<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

use Phil\AiBundle\Domain\Exception\InvalidAiValue;
use Phil\AiBundle\Domain\ValueObject\Subject;
use Phil\AiBundle\Domain\ValueObject\TaskKind;

/**
 * One task to run: its kind, the subject it is charged to, the locale of its prompts, the values of their
 * placeholders, and the person it runs for, whose own key it may use.
 */
final readonly class TaskRequest
{
    public TaskKind $kind;

    public Subject $subject;

    /**
     * @param array<string, string> $parameters the placeholders of the prompts, by name
     * @param string|null           $owner      the person the task runs for, as `phil/user-secrets-bundle` names them;
     *                                          null for a task of the application itself
     *
     * @throws InvalidAiValue
     */
    public function __construct(
        string $kind,
        string $subject,
        public string $locale,
        public array $parameters = [],
        public ?string $owner = null,
    ) {
        $this->kind = TaskKind::fromString($kind);
        $this->subject = Subject::fromString($subject);
    }
}
