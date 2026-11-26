<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Contract;

use Phil\CoursesBundle\Application\Training\TrainingCost;
use Phil\CoursesBundle\Domain\Model\TranscriptEntry;

/**
 * What a completion cost the organisation, as the training reports of phil/reporting-bundle count it: the fees, the
 * wages of the hours spent, the trainer — whatever the application keeps —, and the part of that cost the law counts.
 * Without one, every completion costs nothing and only its hours are reported.
 */
interface TrainingCosts
{
    /**
     * The cost of the completion in minor units of the configured currency: cents for dollars.
     */
    public function cost(TranscriptEntry $entry): TrainingCost;
}
