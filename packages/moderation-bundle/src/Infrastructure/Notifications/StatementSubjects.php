<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\Notifications;

use Override;
use Phil\ModerationBundle\Infrastructure\Http\StatementController;
use Phil\NotificationsBundle\Application\Contract\SubjectResolver;
use Phil\NotificationsBundle\Application\ResolvedSubject;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What a notification of a statement of reasons stands for when it is shown: its translated title, and its page when
 * the application imports the routes of the statements. The page itself shows it to the member it is addressed to only.
 */
final readonly class StatementSubjects implements SubjectResolver
{
    public const string SUBJECT = 'moderation-statement';

    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urls,
    ) {
    }

    #[Override]
    public function resolve(SubjectReference $subject, ?string $locale): ?ResolvedSubject
    {
        if (self::SUBJECT !== $subject->type) {
            return null;
        }

        try {
            $url = $this->urls->generate(StatementController::SHOW_ROUTE, ['id' => $subject->id], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (RouteNotFoundException) {
            $url = null;
        }

        return new ResolvedSubject($this->translator->trans('statement.title', [], 'phil_moderation', $locale), $url);
    }
}
