<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Application\Event;

use Phil\Resource\ResourceReference;

/**
 * A member was mentioned in a subject for the first time, dispatched once the mention is stored: the subject, the
 * member, the author, the content whose visibility was checked — the subject itself, or what it belongs to, such as the
 * page a comment is on — and the identifier the member's notifications go to. Identifiers only.
 */
final readonly class MemberMentioned
{
    public function __construct(
        public ResourceReference $subject,
        public ResourceReference $member,
        public ResourceReference $author,
        public ResourceReference $context,
        public string $recipient,
    ) {
    }
}
