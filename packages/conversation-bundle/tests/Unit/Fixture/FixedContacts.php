<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Fixture;

use Override;
use Phil\ConversationBundle\Application\Contract\ContactRelation;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;

/**
 * Contacts given as pairs, each pair both ways.
 */
final readonly class FixedContacts implements ContactRelation
{
    /**
     * @param list<array{string, string}> $pairs
     */
    public function __construct(
        private array $pairs,
    ) {
    }

    #[Override]
    public function areContacts(ParticipantId $one, ParticipantId $other): bool
    {
        return \in_array([$one->value, $other->value], $this->pairs, true) || \in_array([$other->value, $one->value], $this->pairs, true);
    }
}
