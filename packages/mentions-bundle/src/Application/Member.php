<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Application;

use Phil\MentionsBundle\Domain\Exception\InvalidMention;
use Phil\MentionsBundle\Domain\ValueObject\MemberIdentifier;
use Phil\Resource\ResourceReference;

/**
 * A member the application's directory knows: their reference, the handle typed after `@`, the name shown, the address
 * of their profile if they have one, and the identifier their notifications go to — their reference, `user:42`,
 * unless the directory names another. The reference and the recipient are stable identifiers the application gives,
 * never email addresses: either holding `@` is refused.
 */
final readonly class Member
{
    public string $recipient;

    /**
     * @throws InvalidMention when the handle or the name is empty, or the reference or the recipient holds "@"
     */
    public function __construct(
        public ResourceReference $reference,
        public string $handle,
        public string $name,
        public ?string $url = null,
        ?string $recipient = null,
    ) {
        if ('' === $handle || '' === $name) {
            throw InvalidMention::because(\sprintf('The member "%s" has a handle and a name.', $reference->toString()));
        }

        $this->recipient = MemberIdentifier::checked($recipient ?? $reference->toString());
        MemberIdentifier::checked($reference->toString());
    }
}
