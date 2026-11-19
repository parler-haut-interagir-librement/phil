<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Domain\Repository;

use Phil\ConversationBundle\Domain\Model\ReceptionSettings;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;

/**
 * Where the reception settings are kept: a participant without any has the defaults, everyone may write and nobody is
 * blocked.
 */
interface ReceptionRepository
{
    public function of(ParticipantId $participant): ReceptionSettings;

    public function save(ReceptionSettings $settings): void;

    /**
     * Erases the participant: their settings and blocks, and their identifier from the blocks of others.
     *
     * @return int how many rows were removed
     */
    public function forget(ParticipantId $participant): int;
}
