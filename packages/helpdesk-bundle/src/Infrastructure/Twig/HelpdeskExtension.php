<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Infrastructure\Twig;

use Phil\ConversationBundle\Application\Contract\ParticipantDirectory;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Twig\Attribute\AsTwigFunction;

/**
 * The functions of the bundle's templates: `phil_helpdesk_kit()`, the name under which a recipe of the kit is
 * installed, and `phil_helpdesk_name()`, the name the directory of the conversations gives a participant.
 */
final readonly class HelpdeskExtension
{
    public function __construct(
        private KitComponents $kit,
        private ParticipantDirectory $directory,
    ) {
    }

    /**
     * `{% component (phil_helpdesk_kit('Button')) %}`.
     */
    #[AsTwigFunction(name: 'phil_helpdesk_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }

    #[AsTwigFunction(name: 'phil_helpdesk_name')]
    public function name(?ParticipantId $participant): ?string
    {
        return $participant instanceof ParticipantId ? $this->directory->displayName($participant) : null;
    }
}
