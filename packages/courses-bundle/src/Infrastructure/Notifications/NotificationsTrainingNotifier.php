<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Notifications;

use Override;
use Phil\CoursesBundle\Application\Contract\TrainingNotifier;
use Phil\CoursesBundle\Domain\Model\Obligation;
use Phil\CoursesBundle\Domain\Training\TrainingNotice;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * The notifications of the mandatory training through phil/notifications-bundle: the type `courses.training.<notice>`,
 * about the course, with identifiers only — the assignment, the learner, the cycle and the due date, `YYYY-MM-DD` in
 * UTC —, so that no name nor sentence travels in the message.
 */
final readonly class NotificationsTrainingNotifier implements TrainingNotifier
{
    public const string PREFIX = 'courses.training.';

    public function __construct(
        private Notifications $notifications,
    ) {
    }

    #[Override]
    public function notify(TrainingNotice $notice, string $recipient, Obligation $obligation): void
    {
        $this->notifications->send(
            self::PREFIX.$notice->value,
            $recipient,
            new SubjectReference('course', $obligation->course->value),
            [
                'assignment' => $obligation->assignment->value,
                'learner' => $obligation->learner->value,
                'cycle' => (string) $obligation->cycle,
                'due' => $obligation->dueAt->format('Y-m-d'),
            ],
        );
    }

    /**
     * The types the bundle declares to phil/notifications-bundle, by key: each on the site's centre and by e-mail,
     * the overdue ones of high importance.
     *
     * @return array<string, array{channels: list<string>, importance: string}>
     */
    public static function types(): array
    {
        $types = [];

        foreach (TrainingNotice::cases() as $notice) {
            $types[self::PREFIX.$notice->value] = [
                'channels' => ['site', 'email'],
                'importance' => \in_array($notice, [TrainingNotice::Overdue, TrainingNotice::OverdueManager], true) ? 'high' : 'medium',
            ];
        }

        return $types;
    }
}
