<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\ModerationBundle\Domain\Exception\InvalidModeration;
use Phil\ModerationBundle\Domain\Identifiers;

/**
 * A note of the moderation on a member: read by the moderation only, never by the member, and distinct from the private
 * notes a member keeps for themselves.
 */
final readonly class MemberNote
{
    public const int MAX_LENGTH = 5000;

    public string $body;

    public function __construct(
        public string $id,
        public MemberId $member,
        public ?MemberId $author,
        string $body,
        public DateTimeImmutable $writtenAt,
    ) {
        Identifiers::check($id, 'A note');
        $this->body = Identifiers::text($body, self::MAX_LENGTH, 'A note') ?? throw InvalidModeration::because('A note is not empty.');
    }
}
