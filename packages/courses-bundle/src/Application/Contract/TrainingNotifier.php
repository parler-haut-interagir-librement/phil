<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Contract;

use Phil\CoursesBundle\Domain\Model\Obligation;
use Phil\CoursesBundle\Domain\Training\TrainingNotice;

/**
 * Tells a person about an assigned course: the learner it is assigned to, or the person who assigned it. The bundle's
 * adapter sends a notification of phil/notifications-bundle, identifiers only; an application writes its own to use
 * another channel.
 */
interface TrainingNotifier
{
    /**
     * @param string $recipient the stable identifier of the person told
     */
    public function notify(TrainingNotice $notice, string $recipient, Obligation $obligation): void;
}
