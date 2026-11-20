<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Fixture;

use Override;
use Phil\CoursesBundle\Application\Contract\TrainingNotifier;
use Phil\CoursesBundle\Domain\Model\Obligation;
use Phil\CoursesBundle\Domain\Training\TrainingNotice;

/**
 * Keeps the notices of the mandatory training, each as `<notice> <recipient> <course> <cycle>`.
 */
final class RecordingNotifier implements TrainingNotifier
{
    /** @var list<string> */
    public private(set) array $sent = [];

    #[Override]
    public function notify(TrainingNotice $notice, string $recipient, Obligation $obligation): void
    {
        $this->sent[] = \sprintf('%s %s %s %d', $notice->value, $recipient, $obligation->course->value, $obligation->cycle);
    }

    /**
     * @return list<string> the notices sent since the last call
     */
    public function take(): array
    {
        $sent = $this->sent;
        $this->sent = [];

        return $sent;
    }
}
