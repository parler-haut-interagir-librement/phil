<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\Notifications;

use Override;
use Phil\ErrorTracking\Contract\ErrorStore;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTrackingBundle\Infrastructure\Http\AdminController;
use Phil\NotificationsBundle\Application\Contract\SubjectResolver;
use Phil\NotificationsBundle\Application\ResolvedSubject;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Says what the subject of an alert stands for when it is shown: the title of the group, as the store keeps it, and its
 * administration page when those pages are enabled and their routes imported. A group purged since has no label.
 */
final readonly class ErrorGroupSubjects implements SubjectResolver
{
    public function __construct(
        private ErrorStore $store,
        private ?UrlGeneratorInterface $urls = null,
    ) {
    }

    #[Override]
    public function resolve(SubjectReference $subject, ?string $locale): ?ResolvedSubject
    {
        if (NotificationAlerter::SUBJECT !== $subject->type) {
            return null;
        }

        $group = $this->store->find($subject->id);

        if (!$group instanceof ErrorGroup) {
            return null;
        }

        return new ResolvedSubject($group->title, $this->url($group));
    }

    private function url(ErrorGroup $group): ?string
    {
        try {
            return $this->urls?->generate(AdminController::SHOW_ROUTE, ['fingerprint' => $group->fingerprint], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (RouteNotFoundException) {
            return null;
        }
    }
}
