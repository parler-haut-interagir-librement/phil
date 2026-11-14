<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Contract;

use Phil\NotificationsBundle\Application\ResolvedSubject;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * Says what a subject reference stands for when a notification is shown. Each resolver answers for the kinds of subject
 * it knows and returns null for the others; the first answer wins.
 */
interface SubjectResolver
{
    public function resolve(SubjectReference $subject, ?string $locale): ?ResolvedSubject;
}
