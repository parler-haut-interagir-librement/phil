<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application;

use Phil\NotificationsBundle\Application\Contract\RecipientDirectory;
use Phil\NotificationsBundle\Application\Contract\SubjectResolver;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Writes a notification out when it is shown or sent: its type's title and body, translated in the `phil_notifications`
 * domain with `%actor%` — the actor's name —, `%subject%` — the subject's label —, `%count%` — how many it stands for
 * — and each data entry by its key, `%event%`; and the subject's URL. What it says is looked up from its references at
 * that moment, never stored: a renamed subject or actor shows under its new name.
 */
final readonly class NotificationRenderer
{
    /**
     * @param iterable<SubjectResolver> $resolvers
     */
    public function __construct(
        private TranslatorInterface $translator,
        private NotificationTypes $types,
        private RecipientDirectory $directory,
        private iterable $resolvers = [],
    ) {
    }

    public function render(Notification $notification, ?string $locale = null): RenderedNotification
    {
        $subject = null;

        if ($notification->subject instanceof SubjectReference) {
            foreach ($this->resolvers as $resolver) {
                $subject = $resolver->resolve($notification->subject, $locale);

                if ($subject instanceof ResolvedSubject) {
                    break;
                }
            }
        }

        $parameters = [
            '%actor%' => null === $notification->actor ? '' : $this->directory->displayName($notification->actor) ?? '',
            '%subject%' => $subject->label ?? '',
            '%count%' => $notification->count,
        ];

        foreach ($notification->data->values as $key => $value) {
            $parameters['%'.$key.'%'] = $value;
        }

        $type = $this->types->has($notification->type) ? $this->types->get($notification->type) : new NotificationType($notification->type);

        return new RenderedNotification(
            $notification,
            $this->translator->trans($type->title, $parameters, NotificationType::DOMAIN, $locale),
            $this->translator->trans($type->body, $parameters, NotificationType::DOMAIN, $locale),
            $subject?->url,
        );
    }
}
