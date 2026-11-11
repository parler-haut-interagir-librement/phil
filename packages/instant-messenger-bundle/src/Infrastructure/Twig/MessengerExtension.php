<?php

declare(strict_types=1);

namespace Phil\InstantMessengerBundle\Infrastructure\Twig;

use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\KnownMessenger;
use Phil\InstantMessenger\MessengerType;
use Twig\Attribute\AsTwigFilter;

/**
 * Handles in templates: as people read them, the link that opens a conversation, and the service's name.
 */
final readonly class MessengerExtension
{
    #[AsTwigFilter(name: 'messenger_display')]
    public function display(?Handle $handle): string
    {
        return $handle?->display() ?? '';
    }

    #[AsTwigFilter(name: 'messenger_link')]
    public function link(?Handle $handle): ?string
    {
        return $handle?->link();
    }

    /**
     * The service's own name of an international service, `WhatsApp`; the case's name of another.
     */
    #[AsTwigFilter(name: 'messenger_label')]
    public function label(MessengerType|Handle|null $value): string
    {
        $type = $value instanceof Handle ? $value->type : $value;

        return match (true) {
            !$type instanceof MessengerType => '',
            $type instanceof KnownMessenger => $type->label(),
            default => $type->name,
        };
    }
}
