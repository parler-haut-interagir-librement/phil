<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Domain\Reception;

/**
 * Who may start a conversation with a participant: everyone, the participant's contacts as the application knows
 * them, or nobody.
 */
enum WhoCanWrite: string
{
    case Everyone = 'everyone';
    case Contacts = 'contacts';
    case Nobody = 'nobody';
}
