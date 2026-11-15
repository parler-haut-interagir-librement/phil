<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Notifications;

use Override;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Phil\TermsBundle\Application\Contract\TermsNotifier;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;

/**
 * The notices of the terms through `phil/notifications-bundle`: a publication goes to the audience
 * `phil_terms:<policy>`, which `OutstandingAudience` turns into the members who have not accepted the version; a
 * reminder goes to its members. Both are about the subject `phil_terms_policy:<policy>` and carry the version number
 * as their `version` data, under the two types the bundle declares.
 */
final readonly class NotificationsNotifier implements TermsNotifier
{
    public const string SUBJECT_TYPE = 'phil_terms_policy';

    public const string PUBLISHED_TYPE = 'phil_terms.published';

    public const string REMINDER_TYPE = 'phil_terms.reminder';

    public function __construct(
        private Notifications $notifications,
        private string $publishedType,
        private string $reminderType,
    ) {
    }

    #[Override]
    public function published(PolicyVersion $version): void
    {
        $this->notifications->sendToAudience($this->publishedType, OutstandingAudience::PREFIX.$version->policy, new SubjectReference(self::SUBJECT_TYPE, $version->policy), ['version' => $version->number]);
    }

    #[Override]
    public function remind(string $policy, int $version, array $members): void
    {
        $this->notifications->send($this->reminderType, $members, new SubjectReference(self::SUBJECT_TYPE, $policy), ['version' => $version]);
    }
}
