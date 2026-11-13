<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application\View;

use DateTimeImmutable;

/**
 * One share of an item: the level, who it is given to — a member or an access group — and when it ends, if it does.
 */
final readonly class Share
{
    public const string MEMBER = 'member';

    public const string GROUP = 'group';

    public function __construct(
        public string $level,
        public string $granteeKind,
        public string $grantee,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }

    public function isGroup(): bool
    {
        return self::GROUP === $this->granteeKind;
    }
}
