<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use DateTimeImmutable;
use Override;
use Phil\ModerationBundle\Application\Contract\SandboxPolicy;
use Phil\ModerationBundle\Domain\Contract\SandboxRepository;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;

/**
 * Holds the content of a member until the moderation approved one of theirs, for the target types configured; a type
 * not listed is never held.
 */
final readonly class FirstPostSandbox implements SandboxPolicy
{
    /**
     * @param list<string> $types
     */
    public function __construct(
        private SandboxRepository $sandbox,
        private array $types,
    ) {
    }

    #[Override]
    public function holds(MemberId $author, string $targetType): bool
    {
        return \in_array($targetType, $this->types, true) && !$this->sandbox->clearedAt($author) instanceof DateTimeImmutable;
    }
}
